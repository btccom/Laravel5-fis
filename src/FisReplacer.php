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
        /** @var Response $response */
        $response = $next($request);

        if (!method_exists($response, 'content')) return $response;

        $content = $response->content();

        /** @var Fis $fis */
        $fis = app('fis');

        if (!$fis->useMap()) return $response;

        $resource_map = $fis->buildResourceMap();
        $content = str_replace($fis->getResourcePlaceHolder(), $fis->resourceMapToString($resource_map), $content);

        $response->setContent($content);
        return $response;
    }
}
