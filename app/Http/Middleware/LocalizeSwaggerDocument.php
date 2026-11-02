<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Localizes the human-readable fields of the OpenAPI document for the current locale.
 * Paths, operationIds, schemas, properties, and technical values remain
 * stable to avoid breaking clients or API examples.
 */
final class LocalizeSwaggerDocument
{
    /** @var list<string> */
    private const TRANSLATABLE_FIELDS = [
        'title',
        'summary',
        'description',
    ];

    /** @var array<string, string> Source strings from the OpenAPI spec → domain */
    private const SOURCE_DOMAINS = [
        'Gestão de Avarias API' => 'common',
        'Documentação OpenAPI da aplicação de gestão de tickets, equipamentos, auditoria e relatórios.' => 'tickets',
        'Servidor Principal da API' => 'common',
        'Token de autenticação personalizado baseado em cabeçalho' => 'auth',
        'Autenticação baseada em Token JWT' => 'auth',
        'Catálogo de peças, fornecedores, movimentos de stock, dashboard e relatórios' => 'stock',
        'Gestão administrativa de peças, fornecedores, taxas de IVA, categorias e planos de manutenção' => 'stock',
    ];

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! str_ends_with($request->path(), 'docs/openapi-json')) {
            return $response;
        }

        $content = $response->getContent();
        $document = $content !== false ? json_decode($content, true) : null;

        if (is_array($document)) {
            $this->translateFields($document);
            $encoded = json_encode($document, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if (is_string($encoded)) {
                $response->setContent($encoded);
                $response->headers->set('Content-Type', 'application/json');
            }
        }

        return $response;
    }

    /** @param array<string|int, mixed> $node */
    private function translateFields(array &$node): void
    {
        foreach ($node as $key => &$value) {
            if (is_array($value)) {
                $this->translateFields($value);

                continue;
            }

            if (is_string($value) && in_array((string) $key, self::TRANSLATABLE_FIELDS, true)) {
                $domain = self::SOURCE_DOMAINS[$value] ?? null;
                $value = $domain !== null ? __($domain.'.'.$value) : $value;
            }
        }
    }
}
