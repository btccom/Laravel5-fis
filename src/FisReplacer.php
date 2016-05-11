<?php

namespace BTCCOM\Fis;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

class FisReplacer {
    const PLACEHOLDER = '<!-- fis::resource -->';

    protected function getAssetsFilePath(string $name) {
        foreach ([$name, 'assets'] as $v) {
            $path = resource_path("assets_map/$v.json");
            if (is_readable($path)) return $path;
        }

        throw new \InvalidArgumentException('Assets File Not Found');
    }

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

        if (!property_exists($response, 'original') || !$response->original instanceof View) {
            return $response;
        }

        $content = $response->content();

        if (false === strpos($content, static::PLACEHOLDER)) return $response;

        $name = $response->original->name();
        $path = $this->getAssetsFilePath($name);

        /** @var Fis $fis */
        $fis = app('fis', $path ? [$path] : []);

        if (null === $page_resource = $fis->getPageResource($name)) {
            throw new \InvalidArgumentException("view $name not found in file $path");
        }

        $resource_map = $fis->buildResourceMap($page_resource);

        $content = str_replace(static::PLACEHOLDER, $fis->resourceMapToString($resource_map), $content);

        $response->setContent($content);

        return $response;
    }
}
