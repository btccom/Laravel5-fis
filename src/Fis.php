<?php

namespace BTCCOM\Fis;

class Fis {
    /** @var array */
    protected $assets_map;
    protected $resourcePlaceHolder = '<!-- fis::resource -->';
    protected $asyncScripts = [];
    protected $syncScripts = [];

    /**
     * @return string
     */
    public function getResourcePlaceHolder() {
        return $this->resourcePlaceHolder;
    }

    public function useFramework($map_name = null) {
        $path = $this->getAssetsFilePath($map_name ?? 'assets');
        $this->setAssetsMap($path);

        return $this->resourcePlaceHolder;
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

    public function addScript(string $type, array $ids) {
        if ($type == 'async') {
            $this->asyncScripts = array_merge($this->asyncScripts, $ids);
        } else if ($type == 'sync') {
            $this->syncScripts= array_merge($this->syncScripts, $ids);
        } else {
            throw new \InvalidArgumentException("invalid script type: $type");
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

    /*
     * 构建 resourceMap
     * 思路：模块如被同步引用，则依赖的子模块按照代码实现进行同步或异步引用；
     *      模块如被异步引用，则依赖的所有子模块均按照异步引用
     */
    public function buildResourceMap() {
        $resource_map = [
            'async' => [],
            'sync' => [],
            'pkg' => []
        ];

        $this->digestNode([
            'type' => 'php',
            'deps' => $this->syncScripts,
            'extras' => [
                'async' => $this->asyncScripts
            ]
        ], $resource_map, 'sync');  //从页面开始均为 sync 类型

        return $resource_map;
    }

    protected function digestNode(array $node, array &$resource_map, string $base_mode = 'sync') {
        if ($node['type'] == 'js') {
            $standard_node = array_only($node, ['type', 'deps', 'pkg']);
            $standard_node['url'] = $node['uri'];       //有毒，前后端字段不一致

            if (isset($standard_node['deps'])) {
                $standard_node['deps'] = array_filter(array_map(function(string $d) {
                    return isset($this->assets_map['res'][$d]) ? $this->assets_map['res'][$d]['extras']['moduleId'] : null;
                }, $standard_node['deps']));
                if (!count($standard_node['deps'])) unset($standard_node['deps']);
            }

            // 如 async 模块在 sync 中已加载,则忽略
            if (!isset($resource_map['sync'][$node['extras']['moduleId']])) {
                $resource_map[$base_mode][$node['extras']['moduleId']] = $standard_node;
            }

            if (isset($standard_node['pkg'])) {
                $pkg = $this->assets_map['pkg'][$standard_node['pkg']];
                $standard_pkg = array_only($pkg, 'type');
                $standard_pkg['url'] = $pkg['uri'];
                $resource_map['pkg'][$standard_node['pkg']] = $standard_pkg;
            }
        }

        if (isset($node['deps'])) {
            $deps = $node['deps'];

            foreach ($deps as $dep) {
                if (isset($this->assets_map['res'][$dep])) {
                    $sub_node = $this->assets_map['res'][$dep];
                    $this->digestNode($sub_node, $resource_map, $base_mode);
                }
            }
        }

        if (isset($node['extras']['async'])) {
            $deps = $node['extras']['async'];
            foreach ($deps as $dep) {
                if (isset($this->assets_map['res'][$dep])) {
                    $sub_node = $this->assets_map['res'][$dep];
                    $this->digestNode($sub_node, $resource_map, 'async');
                }
            }
        }
    }

    public function resourceMapToString(array $resource_map) {
        // 输出同步脚本
        $pkg_used = [];
        $output = '';
        foreach ($resource_map['sync'] as $s) {
            if (isset($s['pkg']) && isset($resource_map['pkg'][$s['pkg']])) {
                if (isset($pkg_used[$s['pkg']])) continue;

                $pkg_used[$s['pkg']] = true;
                $url = $resource_map['pkg'][$s['pkg']]['url'];
            } else {
                $url = $s['url'];
            }
            $output .= sprintf('<script src="%s"></script>', $url);
        }

        // 输出异步资源
        if ($resource_map['async']) {
            $map = json_encode([
                'res' => $resource_map['async'],
                'pkg' => $resource_map['pkg'],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $output .= "<script>require.resourceMap($map)</script>";
        }

        return $output;
    }
}