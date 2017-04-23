<?php

namespace BTCCOM\Fis;

class Fis {
    /** @var array */
    protected $assets_map;
    protected $jsPlaceHolder = '<!-- fis::js -->';
    protected $cssPlaceHolder = '<!-- fis::css --> ';

    protected $asyncQueue = [];
    protected $syncQueue = [];
    protected $cssQueue = [];

    /**
     * @return string
     */
    public function getJsPlaceHolder() {
        return $this->jsPlaceHolder;
    }

    public function getCssPlaceHolder() {
        return $this->cssPlaceHolder;
    }

    public function useFramework($map_name = null) {
        $path = $this->getAssetsFilePath($map_name ?? 'assets');
        $this->setAssetsMap($path);

        return $this->getJsPlaceHolder();
    }

    protected function getAssetsFilePath(string $name) {
        foreach ([$name, 'assets'] as $v) {
            $path = resource_path("assets_map/$v.json");
            if (is_readable($path)) return $path;
        }

        throw new \InvalidArgumentException('Assets File Not Found');
    }

    public function setAssetsMap(string $assets_path) {
        $this->assets_map = json_decode(file_get_contents($assets_path), true);

        if (json_last_error() != JSON_ERROR_NONE ||
            !isset($this->assets_map['res']) ||
            !isset($this->assets_map['pkg'])) {
            throw new \InvalidArgumentException('invalid assets map');
        }
    }

    public function useMap() {
        return !is_null($this->assets_map);
    }

    public function addDep(string $type, array $ids) {
        // 移除已添加过的依赖
        $ids = array_filter($ids, function ($id) {
            return !isset($this->asyncQueue[$id]) && !isset($this->syncQueue[$id]);
        });

        if ($type == 'async') {
            foreach ($ids as $id) {
                $this->addAsyncQueue($id);
            }
        } else if ($type == 'sync') {
            foreach ($ids as $id) {
                $this->addSyncQueue($id);
            }
        } else {
            throw new \InvalidArgumentException("invalid script type: $type");
        }
    }

    protected function addAsyncQueue(string $id) {
        if (!isset($this->assets_map['res'][$id])) {
            throw new \InvalidArgumentException('invalid id: ' . $id);
        }

        $type = $this->assets_map['res'][$id]['type'];
        if ($type == 'js') {
            $this->asyncQueue[$id] = 1;
        } else if ($type == 'css') {
            $this->cssQueue[$id] = 1;
        } else {
            throw new \InvalidArgumentException("invalid type: id = $id, type = $type");
        }

        // 异步资源的依赖均为异步
        if (isset($this->assets_map['res'][$id]['deps'])) {
            $this->addDep('async', $this->assets_map['res'][$id]['deps']);
        }

        if (isset($this->assets_map['res'][$id]['extras']['async'])) {
            $this->addDep('async', $this->assets_map['res'][$id]['extras']['async']);
        }
    }

    protected function addSyncQueue(string $id) {
        if (!isset($this->assets_map['res'][$id])) {
            throw new \InvalidArgumentException('invalid id: ' . $id);
        }

        $type = $this->assets_map['res'][$id]['type'];
        if ($type == 'js') {
            $this->syncQueue[$id] = 1;
        } else if ($type == 'css') {
            $this->cssQueue[$id] = 1;
        } else {
            throw new \InvalidArgumentException("invalid type: id = $id, type = $type");
        }

        if (isset($this->assets_map['res'][$id]['deps'])) {
            $this->addDep('sync', $this->assets_map['res'][$id]['deps']);
        }

        if (isset($this->assets_map['res'][$id]['extras']['async'])) {
            $this->addDep('async', $this->assets_map['res'][$id]['extras']['async']);
        }
    }

    public function find(string $path) {
        $path = ltrim($path, '/');

        if (!isset($this->assets_map['res'][$path])) {
            throw new \InvalidArgumentException("resource file not found: $path");
        }

        $info = $this->assets_map['res'][$path];

        if (!isset($info['pkg'])) {
            return [
                'type' => 'res',
                'info' => $info
            ];
        }

        // pkg 中应有对应的字段
        assert(isset($this->assets_map['pkg'][$info['pkg']]));

        return [
            'type' => 'pkg',
            'info' => $this->assets_map['pkg'][$info['pkg']]
        ];
    }

    public function uri(string $path) {
        $resource = $this->find($path);
        return $resource['info']['uri'];
    }

    public function tag(string $path) {
        $resource = $this->find($path);

        if ($resource['info']['type'] == 'js') {
            return sprintf('<script src="%s"></script>', $resource['info']['uri']);
        } else if ($resource['info']['type'] == 'css') {
            return sprintf('<link rel="stylesheet" href="%s"/>', $resource['info']['uri']);
        } else {    //来自东方的神秘类型
            throw new \InvalidArgumentException("unknown resource type: ${$resource['info']['type']}, path: $path");
        }
    }

    public function dump() {
        return [
            'sync' => $this->syncQueue,
            'async' => $this->asyncQueue,
            'css' => $this->cssQueue,
        ];
    }

    public function renderCss() {
        $pkg = [];
        $output = [];
        $html = '<link rel="stylesheet" href="%s" />';
        foreach ($this->cssQueue as $css => $_) {
            if (isset($this->assets_map['res'][$css]['pkg'])) {
                $pkg_name = $this->assets_map['res'][$css]['pkg'];
                if (!isset($pkg[$pkg_name])) {
                    $pkg[$pkg_name] = 1;
                    $output[] = sprintf($html, $this->assets_map['pkg'][$pkg_name]['uri']);
                }
            } else {
                $output[] = sprintf($html, $this->assets_map['res'][$css]['uri']);
            }
        }

        if (!$output) return '';

        return join("\n", $output) . "\n";
    }

    public function renderJs() {
        $pkg = [];
        $output = [];
        $html = '<script src="%s"></script>';

        // 同步
        foreach ($this->syncQueue as $js => $_) {
            if (isset($this->assets_map['res'][$js]['pkg'])) {
                $pkg_name = $this->assets_map['res'][$js]['pkg'];
                if (!isset($pkg[$pkg_name])) {
                    $pkg[$pkg_name] = 1;
                    $output[] = sprintf($html, $this->assets_map['pkg'][$pkg_name]['uri']);
                }
            } else {
                $output[] = sprintf($html, $this->assets_map['res'][$js]['uri']);
            }
        }

        if (!$output) return '';

        return join("\n", $output) . "\n";
    }

    public function renderResourceMap() {
        $async = [
            'res' => [],
            'pkg' => [],
        ];
        foreach ($this->asyncQueue as $js => $_) {
            $async['res'][$js] = $this->assets_map['res'][$js];
            if (isset($this->assets_map['res'][$js]['pkg'])) {
                $pkg_name = $this->assets_map['res'][$js]['pkg'];
                $async['pkg'][$pkg_name] = $this->assets_map['pkg'][$pkg_name];
            }
        }

        if (!$async['res'] && !$async['pkg']) return '';

        return sprintf('<script>require.resourceMap(%s)</script>', json_encode($async, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}