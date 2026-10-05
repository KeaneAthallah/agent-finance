<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settings (spec §44): read-only display of the platform
 * configuration. Settings change by environment (config
 * files and env), not through the UI — this page makes the
 * effective configuration visible to platform staff.
 */
final class SettingsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        \assert($user instanceof User);

        $providers = config('agent-finance.ai.providers', []);
        $provider = (string) config('agent-finance.ai.provider');

        return Inertia::render('settings/index', [
            'settings' => [
                'ai' => [
                    'provider' => $provider,
                    'default_model' => (string) config('agent-finance.ai.default_model'),
                    'embedding_model' => (string) config('agent-finance.ai.embedding_model'),
                    'key_configured' => filled($providers[$provider]['key'] ?? null),
                    'available_providers' => array_keys($providers),
                ],
                'ocr' => [
                    'driver' => (string) config('agent-finance.ocr.driver'),
                    'language' => (string) config('agent-finance.ocr.language'),
                ],
                'sihandal' => [
                    'base_url' => filled(config('agent-finance.sihandal.base_url'))
                        ? (string) config('agent-finance.sihandal.base_url')
                        : '(fake client — offline)',
                    'timeout' => (int) config('agent-finance.sihandal.timeout'),
                    'cache_ttl' => (int) config('agent-finance.sihandal.cache_ttl'),
                ],
                'validation' => [
                    'low_confidence' => (float) config('agent-finance.validation.low_confidence'),
                    'review_confidence' => (float) config('agent-finance.validation.review_confidence'),
                ],
                'documents' => [
                    'disk' => (string) config('agent-finance.documents.disk'),
                    'max_size_kb' => (int) config('agent-finance.documents.max_size_kb'),
                    'allowed_extensions' => config('agent-finance.documents.allowed_extensions', []),
                ],
                'queue' => (string) config('queue.default'),
            ],
        ]);
    }
}
