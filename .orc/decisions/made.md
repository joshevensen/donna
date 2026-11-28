## Text from the database shown as markdown in a Donna bubble
- escape markdown metacharacters before building the string — #11 — names can come from model extraction; a name must never become a link or formatting

## Formatting data the API returns for the web app
- the API returns JSON; the web app formats it in a pure `resources/js/lib` function — #11 — `CLAUDE.md`: the API is JSON only and the native app will read the same endpoints

## ARIA role for a list of command buttons
- a plain `<ul>` with `aria-label`, no listbox/menu role — #11 — listbox needs `option` children; buttons inside a list need no extra role

## Endpoint that passes another class's report straight through
- test the endpoint's auth and shape only; the class's own test owns its logic — #11 — `CLAUDE.md`: prune tests that catch the same defect
