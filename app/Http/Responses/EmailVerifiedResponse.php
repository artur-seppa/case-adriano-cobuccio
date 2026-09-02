<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\VerifyEmailResponse as VerifyEmailResponseContract;
use Symfony\Component\HttpFoundation\Response;

/**
 * The email-verification link is clicked from an email in a browser, so a bare
 * 204 renders as a blank page. Until the SPA owns this screen (it will point the
 * link at itself), return a tiny self-contained confirmation page.
 */
class EmailVerifiedResponse implements VerifyEmailResponseContract
{
    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return response()->json(['message' => 'E-mail verificado.', 'verified' => true]);
        }

        $html = <<<'HTML'
            <!doctype html>
            <meta charset="utf-8">
            <title>E-mail verificado</title>
            <div style="font:16px/1.5 system-ui,sans-serif;max-width:32rem;margin:20vh auto;text-align:center;color:#1b1b18">
              <p style="font-size:2rem;margin:0 0 .5rem">✓</p>
              <h1 style="font-size:1.1rem;margin:0 0 .25rem">E-mail verificado</h1>
              <p style="color:#706f6c;margin:0">Você já pode fechar esta aba e voltar ao app.</p>
            </div>
            HTML;

        return response($html);
    }
}
