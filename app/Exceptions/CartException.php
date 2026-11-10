<?php

namespace App\Exceptions;

use RuntimeException;

/** A cart or checkout rule was broken. The message is written for the customer and safe to show. */
class CartException extends RuntimeException {}
