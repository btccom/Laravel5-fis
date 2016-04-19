<?php

namespace BTCCOM\Fis;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

class FisReplacer {
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next) {
        /** @var Fis $fis */
        $fis = app('fis');
        /** @var Response $response */
        $response = $next($request);
        if (!$response->original instanceof View) {
            return $response;
        }

        $content = $response->content();
        $name = $response->original->name();
        $page_resource = $fis->getPageResource($name);
        $resource_map = $fis->buildResourceMap($page_resource);

        $content = str_replace('<!-- fis::resource -->', $fis->resourceMapToString($resource_map), $content);

        $response->setContent($content);

        return $response;
    }
}