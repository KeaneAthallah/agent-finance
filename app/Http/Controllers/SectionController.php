<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase-1 placeholders for sections not yet built (spec
 * §44). Each page states what is planned and when it lands
 * (see docs/IMPLEMENTATION_PLAN.md). Navigation and layout
 * are real from day one.
 */
class SectionController extends Controller
{
    public function review(Request $request): Response
    {
        return $this->placeholder($request, 'review', 'Review Queue', 'Documents requiring human attention, ordered by risk.');
    }

    public function validation(Request $request): Response
    {
        return $this->placeholder($request, 'validation', 'Validation', 'Validation engine results per document.');
    }

    public function activity(Request $request): Response
    {
        return $this->placeholder($request, 'activity', 'Activity', 'Audit trail across the platform.');
    }

    private function placeholder(Request $request, string $view, string $title, string $description): Response
    {
        // Reserved for later phases; page renders with role-aware layout.
        unset($request);

        return Inertia::render('placeholder', [
            'title' => $title,
            'description' => $description,
            'phase' => $this->phaseFor($view),
        ]);
    }

    private function phaseFor(string $view): string
    {
        return match ($view) {
            'review', 'validation', 'activity' => 'Phase 5 — Review, Validation & Audit',
            default => 'Upcoming phase',
        };
    }
}
