<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The user has consumed their daily AI budget (roadmap P0-2). The controller
 * maps this to a friendly 429 JSON response — an over-budget user never gets
 * a 500 or a silent half-answer.
 */
final class BudgetExceededException extends RuntimeException {}
