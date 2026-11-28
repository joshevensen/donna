<?php

namespace App\Files;

use RuntimeException;

/**
 * An upload Donna can't use. The message is the reason shown on its chip.
 */
class UnsupportedFile extends RuntimeException {}
