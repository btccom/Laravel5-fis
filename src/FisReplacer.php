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

        $content = preg_replace_callback('="@btccom\.(sync|async):(.+?)"=', function($matches) use ($fis) {
            $type = $matches[1];
            $deps = array_map(function($d) {
                return trim($d, "'\"");
            }, explode('|', $matches[2]));

            $fis->addDep($type, $deps);
        }, $content);

        $content = str_replace($fis->getCssPlaceHolder(), $fis->renderCss(), $content);
        $content = str_replace($fis->getJsPlaceHolder(), $fis->renderJs() . $fis->renderResourceMap(), $content);

        $response->setContent($content);
        return $response;
    }
}
