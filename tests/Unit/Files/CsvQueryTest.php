<?php

use App\Files\Csv;
use App\Files\CsvQuery;

/*
 | Totals and filtering are always code. Each case is a question Donna might
 | ask of an export, against one fixture.
 */

const PAYOUTS = <<<'CSV'
Date,Payout,Amount,Status,Note
2026-11-03,po_1,$812.40,Paid,
2026-11-04,po_2,"$1,220.00",Paid,"Big one, wholesale"
2026-11-11,po_3,$640.00,In transit,
2026-11-13,po_4,"$1,030.00",In transit,late
2026-11-14,po_5,$470.00,In transit,
2026-11-24,po_6,n/a,Pending,
2026-11-25,po_7,($99.00),Refunded,
CSV;

function ask(array $query, int $limit = 50): array
{
    return (new CsvQuery)->run(Csv::parse(PAYOUTS), $query, $limit);
}

it('filters rows, comparing money as numbers, dates as dates and text without regard to case', function (array $where, array $payouts) {
    expect(array_column(ask(['where' => $where])['rows'], 1))->toBe($payouts);
})->with([
    'text equals' => [[['column' => 'status', 'op' => '=', 'value' => 'in TRANSIT']], ['po_3', 'po_4', 'po_5']],
    'money greater than' => [[['column' => 'Amount', 'op' => '>', 'value' => '1000']], ['po_2', 'po_4']],
    'a negative in parentheses' => [[['column' => 'Amount', 'op' => '<', 'value' => '0']], ['po_7']],
    'date on or after' => [[['column' => 'Date', 'op' => '>=', 'value' => 'Nov 14, 2026']], ['po_5', 'po_6', 'po_7']],
    'two conditions, both required' => [[['column' => 'Status', 'op' => '=', 'value' => 'Paid'], ['column' => 'Amount', 'op' => '<', 'value' => '1000']], ['po_1']],
    'contains' => [[['column' => 'Note', 'op' => 'contains', 'value' => 'WHOLESALE']], ['po_2']],
    'in a list' => [[['column' => 'Status', 'op' => 'in', 'values' => ['Pending', 'Refunded']]], ['po_6', 'po_7']],
    'in a list sent as one string' => [[['column' => 'Status', 'op' => 'in', 'value' => 'Pending, Refunded']], ['po_6', 'po_7']],
    'in a list sent as JSON text' => [[['column' => 'Status', 'op' => 'in', 'value' => '["Pending","Refunded"]']], ['po_6', 'po_7']],
    'not empty' => [[['column' => 'Note', 'op' => 'not_empty']], ['po_2', 'po_4']],
    'not equal' => [[['column' => 'Status', 'op' => '!=', 'value' => 'Paid'], ['column' => 'Status', 'op' => '!=', 'value' => 'In transit']], ['po_6', 'po_7']],
]);

it('groups and totals exactly, to the cent, and orders by a computed column', function () {
    $result = ask([
        'group_by' => ['Status'],
        'aggregate' => [['fn' => 'count'], ['fn' => 'sum', 'column' => 'Amount']],
        'order_by' => [['column' => 'sum(Amount)', 'direction' => 'desc']],
    ]);

    expect($result['columns'])->toBe(['Status', 'count', 'sum(Amount)'])
        ->and($result['rows'])->toBe([
            ['In transit', '3', '2140.00'],
            ['Paid', '2', '2032.40'],
            ['Refunded', '1', '-99.00'],
            ['Pending', '1', ''],
        ])
        ->and($result['notes'])->toBe(["sum(Amount): 1 value isn't a number and was left out."]);
});

it('computes over every matching row when there is nothing to group by', function () {
    $result = ask([
        'where' => [['column' => 'Status', 'op' => '=', 'value' => 'In transit']],
        'aggregate' => [['fn' => 'avg', 'column' => 'Amount'], ['fn' => 'min', 'column' => 'Amount'], ['fn' => 'max', 'column' => 'Date'], ['fn' => 'count', 'column' => 'Note']],
    ]);

    expect($result['matched'])->toBe(3)
        ->and($result['rows'])->toBe([['713.3333', '470.00', '2026-11-14', '1']]);
});

it('returns only as many rows as asked for and says how many there were', function () {
    $result = ask(['order_by' => [['column' => 'Amount', 'direction' => 'desc']]], limit: 2);

    expect(array_column($result['rows'], 1))->toBe(['po_2', 'po_4'])
        ->and($result['total'])->toBe(7);
});

it('refuses a column or an operator that doesn\'t exist, naming what does', function (array $query, string $message) {
    expect(fn () => ask($query))->toThrow(InvalidArgumentException::class, $message);
})->with([
    'unknown column' => [['where' => [['column' => 'Total', 'op' => '=', 'value' => '1']]], 'There is no column "Total". The columns are: Date, Payout, Amount, Status, Note.'],
    'unknown operator' => [['where' => [['column' => 'Amount', 'op' => 'like', 'value' => '1']]], '"like" isn\'t an operator.'],
    'sum of nothing' => [['aggregate' => [['fn' => 'sum']]], 'sum needs a column.'],
    'a list where one value belongs' => [['where' => [['column' => 'Status', 'op' => '=', 'value' => ['Paid', 'Pending']]]], '"=" compares with one value, not a list.'],
]);

it('reads a file split on semicolons, with quoted fields that hold line breaks', function () {
    $csv = Csv::parse("Name;Note\nAnita;\"two\nlines\"\n\nDana;ok\n");

    expect($csv->columns)->toBe(['Name', 'Note'])
        ->and($csv->rows)->toBe([['Anita', "two\nlines"], ['Dana', 'ok']])
        ->and($csv->totalRows)->toBe(2);
});

it('adds up amounts written the European way, with a comma for the decimal point', function () {
    $csv = Csv::parse("Artikel;Betrag\nGarn;12,50\nNadeln;7,25\nWebstuhl;1.000,00\n");

    $result = (new CsvQuery)->run($csv, ['aggregate' => [['fn' => 'sum', 'column' => 'Betrag']]], 50);

    expect($result['rows'])->toBe([['1019.75']]);
});

it('does not take two different order numbers for the same date', function () {
    $csv = Csv::parse("Order,Total\n1001-2,10\n1001-02,20\n");

    $result = (new CsvQuery)->run($csv, ['where' => [['column' => 'Order', 'op' => '=', 'value' => '1001-2']]], 50);

    expect($result['rows'])->toBe([['1001-2', '10']]);
});
