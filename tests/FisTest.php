<?php

namespace BTCCOM\Fis;

use Exception;
use Illuminate\Foundation\Testing\TestCase;

class FisTest extends TestCase {

    protected function getValidFis() {
        return new Fis(__DIR__ . '/fixtures/FisTestFixtures/assets.json');
    }

    public function testAssetsMap() {
        try {
            $this->getValidFis();
        } catch (Exception $e) {
            $this->fail('assets-map 应有效');
        }
    }

    public function testAssetsMap_Invalid() {
        try {
            new Fis(__DIR__ . '/not-exists');
            new Fis(__DIR__ . '/fixtures/FisTestFixtures/invalid-assets.json');
            $this->fail('assets-map 不可用时应抛出异常');
        } catch (Exception $e) {
            //no-op
        }
    }

    public function testUriNotFound() {
        $fis = $this->getValidFis();

        try {
            $fis->find('not-exists');
            $this->fail('assets-map 在找不到对应条目时应报错');
        } catch (Exception $e) {
            //no-op
        }
    }

    public function testUriFound_Standalone() {
        $fis = $this->getValidFis();

        // 获取未打包资源:相对路径
        $resource = $fis->find('lib/dropload/dropload.css');
        $this->assertEquals('res', $resource['type'], '未打包资源的类型应为 res');
        $this->assertEquals('/assets/lib/dropload/dropload.css', $resource['info']['uri'], '未打包资源的相对 uri 错误');

        // 获取未打包资源:绝对路径
        $resource = $fis->find('/lib/dropload/dropload.css');
        $this->assertEquals('res', $resource['type'], '未打包资源的类型应为 res');
        $this->assertEquals('/assets/lib/dropload/dropload.css', $resource['info']['uri'], '未打包资源的相对 uri 错误');
    }

    public function testUriFound_InPackage() {
        $fis = $this->getValidFis();

        $resource = $fis->find('modules/thirdparty/moment.js');
        $this->assertEquals('pkg', $resource['type'], '获取的打包路径错误');
        $this->assertEquals('/assets/lib.js', $resource['info']['uri'], '获取的打包路径错误');
    }

    public function testUri() {
        $fis = $this->getValidFis();

        $this->assertEquals('/assets/lib.js', $fis->uri('modules/thirdparty/moment.js'), '获取资源 uri 错误');
    }

    public function testScriptTag() {
        $fis = $this->getValidFis();

        $this->assertEquals('<script src="/assets/lib.js"></script>', $fis->tag('modules/thirdparty/moment.js'), '应获取正确的 script 标签');
        $this->assertEquals('<script src="/assets/lib/dropload/dropload.js"></script>', $fis->tag('lib/dropload/dropload.js'), '应获取正确的 script 标签');
    }

    public function testLinkTag() {
        $fis = $this->getValidFis();

        $this->assertEquals('<link rel="stylesheet" href="/assets/lib/dropload/dropload.css"/>', $fis->tag('lib/dropload/dropload.css'), '应获取正确的 link 标签');
    }

    public function testScriptTag_Invalid() {
        $fis = $this->getValidFis();

        try {
            $fis->tag('/non-exists');
            $this->fail('生成链接时,不存在的文件应抛出异常');
        } catch (\InvalidArgumentException $e) {
            // no-op
        }
    }

    public function testFindPossibleResource() {
        $fis = $this->getValidFis();

        $view_name = 'non-exists';
        $this->assertNull($fis->getPageResource($view_name));

        $view_name = 'index';
        $page_resource = $fis->getPageResource($view_name);
        $this->assertEquals('index', $page_resource['key'], 'index 名称错误');
        $this->assertEquals('php', $page_resource['type'], 'index 类型错误');
        $this->assertNotEmpty($page_resource['deps'], 'index2 的同步依赖应不为空');
        $this->assertNotEmpty($page_resource['async_deps'], 'index2 的异步依赖应不为空');

        $view_name = 'index2';
        $page_resource = $fis->getPageResource($view_name);
        $this->assertEquals('index2', $page_resource['key'], 'index2 名称错误');
        $this->assertEquals('php', $page_resource['type'], 'index2 类型错误');
        $this->assertNotEmpty($page_resource['deps'], 'index2 的同步依赖应不为空');
        $this->assertEmpty($page_resource['async_deps'], 'index2 的异步依赖应为空');

        $view_name = 'dir.index3';
        $page_resource = $fis->getPageResource($view_name);
        $this->assertEquals('dir/index3', $page_resource['key'], 'dir.index3 名称错误');
        $this->assertEquals('php', $page_resource['type'], 'dir.index3 类型错误');
        $this->assertNotEmpty($page_resource['deps'], 'dir.index3 的同步依赖应不为空');
        $this->assertEmpty($page_resource['async_deps'], 'dir.index3 的异步依赖应为空');
    }

    public function testBuildResourceMap() {
        $fis = $this->getValidFis();

        $resource = $fis->getPageResource('index');
        $resource_map = $fis->buildResourceMap($resource);
        $this->assertEquals([
            'sync' => [
                'modules/A' => [
                    'url' => '/assets/modules/A.js',
                    'type' => 'js',
                    'deps' => [
                        'modules/b',
                    ]
                ],
                'modules/b' => [
                    'url' => '/assets/modules/b.js',
                    'type' => 'js'
                ]
            ],
            'async' => [
                'modules/article' => [
                    'url' => '/assets/modules/article.js',
                    'type' => 'js',
                    'deps' => [
                        'modules/thirdparty/vue',
                        'modules/thirdparty/zepto',
                        'modules/thirdparty/moment'
                    ]
                ],
                'modules/thirdparty/vue' => [
                    'url' => '/assets/modules/thirdparty/vue.js',
                    'type' => 'js',
                    'pkg' => 'p0'
                ],
                'modules/thirdparty/zepto' => [
                    'url' => '/assets/modules/thirdparty/zepto.js',
                    'type' => 'js',
                    'pkg' => 'p0'
                ],
                'modules/thirdparty/moment' => [
                    'url' => '/assets/modules/thirdparty/moment.js',
                    'type' => 'js',
                    'pkg' => 'p0'
                ],
                'modules/c' => [
                    'url' => '/assets/modules/c.js',
                    'type' => 'js'
                ],
            ],
            'pkg' => [
                'p0' => [
                    'url' => '/assets/lib.js',
                    'type' => 'js'
                ]
            ],
        ], $resource_map, 'index resource map 不符合预期');
    }

    public function testResourceMapToString() {
        $fis = $this->getValidFis();

        $resource_map = $fis->buildResourceMap($fis->getPageResource('index'));

        $result = $fis->resourceMapToString($resource_map);
        $this->assertEquals('<script src="/assets/modules/A.js"></script><script src="/assets/modules/b.js"></script><script>require.resourceMap({"res":{"modules/article":{"type":"js","deps":["modules/thirdparty/vue","modules/thirdparty/zepto","modules/thirdparty/moment"],"url":"/assets/modules/article.js"},"modules/thirdparty/vue":{"type":"js","pkg":"p0","url":"/assets/modules/thirdparty/vue.js"},"modules/thirdparty/zepto":{"type":"js","pkg":"p0","url":"/assets/modules/thirdparty/zepto.js"},"modules/thirdparty/moment":{"type":"js","pkg":"p0","url":"/assets/modules/thirdparty/moment.js"},"modules/c":{"type":"js","url":"/assets/modules/c.js"}},"pkg":{"p0":{"type":"js","url":"/assets/lib.js"}}})</script>', $result, 'resource map 序列化结果不符合预期');
    }
}
