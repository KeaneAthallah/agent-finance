<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase-1 placeholders for major sections (spec §44). Each page states what
 * is planned and when it lands (see docs/IMPLEMENTATION_PLAN.md). These are
 * replaced phase by phase; navigation and layout are real from day one.
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

    public function sihandal(Request $request): Response
    {
        return $this->placeholder($request, 'sihandal', 'SIHANDAL', 'Master data and synchronization status.');
    }

    public function reports(Request $request): Response
    {
        return $this->placeholder($request, 'reports', 'Reports', 'Processing, confidence and cost reports.');
    }

    public function activity(Request $request): Response
    {
        return $this->placeholder($request, 'activity', 'Activity', 'Audit trail across the platform.');
    }

    public function settings(Request $request): Response
    {
        return $this->placeholder($request, 'settings', 'Settings', 'System settings overview.');
    }

    public function administration(Request $request): Response
    {
        return $this->placeholder($request, 'admin/index', 'Administration', 'Users, roles, OPDs, document types, AI prompts.');
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
            'reports' => 'Phase 9 — Reports, Admin & Cost',
            'settings', 'admin/index' => 'Phase 9 — Reports, Admin & Cost',
            default => 'Upcoming phase',
        };
    }
}
