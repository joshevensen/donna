<?php

namespace App\Files;

use RuntimeException;

/**
 * An edit that can't be applied as asked: the text isn't there, or isn't
 * unique. The message tells Donna what to do instead; nothing was changed.
 */
class EditRefused extends RuntimeException {}
