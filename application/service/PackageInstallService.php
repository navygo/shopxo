<?php
// +----------------------------------------------------------------------
// | ShopXO 国内领先企业级B2C免费开源电商系统
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2019 http://shopxo.net All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: Devil
// +----------------------------------------------------------------------
namespace app\service;

use think\Db;
use app\service\PluginsAdminService;

/**
 * 软件安装服务层
 * @author  Devil
 * @blog    http://gong.gg/
 * @version 1.0.0
 * @date    2020-09-12
 * @desc    description
 */
class PackageInstallService
{
    /**
     * 获取安装参数
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2021-02-22
     * @desc    description
     * @param   [array]          $params [输入参数]
     */
    public static function RequestInstallParams($params = [])
    {
        // 商店商品id
        $id = empty($params['id']) ? 0 : intval($params['id']);

        // 类型
        $type = empty($params['type']) ? '' : $params['type'];

        return [
            'id'        => $id,
            'type'      => $type,
            'url'       => MyUrl('admin/packageinstall/install'),
            'admin_url' => MyUrl('admin/index/index', ['to_url'=>urldecode(base64_encode(MyUrl('admin/pluginsadmin/index')))]),
        ];
    }

    /**
     * 软件安装
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2021-02-22
     * @desc    description
     * @param   [array]          $params [输入参数]
     */
    public static function Install($params = [])
    {
        // 参数校验
        $ret = self::ParamsCheck($params);
        if($ret['code'] != 0)
        {
            return $ret;
        }

        // 操作类型
        switch($params['opt'])
        {
            // 获取url地址
            case 'url' :
                $ret = self::UrlHandle($params['id']);
                break;

            // 下载软件包
            case 'download' :
                $ret = self::DownloadHandle($params['key']);
                break;

            // 安装软件包
            case 'install' :
                $ret = self::InstallHandle($params);
                break;
        }
        return $ret;
    }

    /**
     * 安装软件包
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2021-02-22
     * @desc    description
     * @param   [array]          $params [输入参数]
     */
    public static function InstallHandle($params)
    {
        // 获取目录文件
        $res = self::DirFileData($params['key']);
        if(!file_exists($res['url']))
        {
            return DataReturn('软件包不存在、请重新安装', -1);
        }

        // 根据插件类型调用安装程序
        switch($params['type'])
        {
            // 功能插件
            case 'plugins' :
                $ret = PluginsAdminService::PluginsUploadHandle($res['url'], $params);
                break;

            // 默认
            default :
                $ret = DataReturn('插件操作类型有误', -1);
        }

        // 移除session
        session($params['key'], null);

        // 删除本地文件
        \base\FileUtil::UnlinkFile($res['url']);

        return $ret;
    }

    /**
     * 下载软件包
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2021-02-22
     * @desc    description
     * @param   [string]          $key [缓存key]
     */
    public static function DownloadHandle($key)
    {
        // 获取下载地址
        $url = session($key);
        if(empty($url))
        {
            return DataReturn('下载地址为空', -1);
        }

        // 获取目录文件
        $res = self::DirFileData($key);

        // 目录不存在则创建
        \base\FileUtil::CreateDir($res['dir'].$res['path']);

        // 下载保存
        if(@file_put_contents($res['url'], RequestGet($url)) !== false)
        {
            return DataReturn('success', 0, $key);
        }
        return DataReturn('插件下载失败', -1);
    }

    /**
     * 获取下载地址
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2021-02-22
     * @desc    description
     * @param   [int]          $goods_id [商品id]
     */
    public static function UrlHandle($goods_id)
    {
        // 获取下载地址
        $url = config('shopxo.store_download_url');
        $data = [
            'goods_id'  => $goods_id,
            'url'       => __MY_URL__,
            'host'      => __MY_HOST__,
            'ip'        => __MY_ADDR__,
            'ver'       => APPLICATION_VERSION,
        ];
        $ret = self::HttpRequest($url, $data);
        if(!empty($ret) && isset($ret['code']) && $ret['code'] == 0)
        {
            $key = md5($ret['data']);
            session($key, $ret['data']);
            $ret['data'] = $key;
        }
        return $ret;
    }
    
    /**
     * 获取软件存储信息
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2021-02-22
     * @desc    description
     * @param   [string]          $key [缓存key]
     */
    public static function DirFileData($key)
    {
        // 将软件包下载到磁盘
        $dir = ROOT;
        $path = 'runtime'.DS.'data'.DS.'plugins_package_install'.DS;
        $filename = $key.'.zip';

        // 目录不存在则创建
        \base\FileUtil::CreateDir($dir.$path);

        return [
            'dir'   => $dir,
            'path'  => $path,
            'file'  => $filename,
            'url'   => $dir.$path.$filename,
        ];
    }

    /**
     * 输入参数校验
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2021-02-22
     * @desc    description
     * @param   [array]           $params [输入参数]
     */
    public static function ParamsCheck($params = [])
    {
        // 请求参数
        $p = [
            [
                'checked_type'      => 'empty',
                'key_name'          => 'id',
                'error_msg'         => '商品id有误',
            ],
            [
                'checked_type'      => 'empty',
                'key_name'          => 'type',
                'error_msg'         => '插件类型有误',
            ],
            [
                'checked_type'      => 'in',
                'key_name'          => 'opt',
                'checked_data'      => ['url', 'download', 'install'],
                'error_msg'         => '操作类型有误',
            ],
        ];
        $ret = ParamsChecked($params, $p);
        if($ret !== true)
        {
            return DataReturn($ret, -1);
        }

        // 下载和安装需要校验key
        if(in_array($params['opt'], ['download', 'install']) && empty($params['key']))
        {
            return DataReturn('操作key有误', -1);
        }

        return DataReturn('success', 0);
    }

    /**
     * 网络请求
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2021-02-22
     * @desc    description
     * @param    [string]          $url  [请求url]
     * @param    [array]           $data [发送数据]
     * @return   [json]                  [请求返回数据]
     */
    public static function HttpRequest($url, $data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FAILONERROR, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $body_string = '';
        if(is_array($data) && 0 < count($data))
        {
            foreach($data as $k => $v)
            {
                $body_string .= $k.'='.urlencode($v).'&';
            }
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body_string);
        }
        $headers = [
            'Content-type: application/x-www-form-urlencoded;charset=UTF-8',
            'X-Requested-With: XMLHttpRequest',
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $reponse = curl_exec($ch);
        $error = curl_errno($ch);
        curl_close($ch);
        if($error)
        {
            return DataReturn("curl出错，错误码:$error", -1);
        }
        return json_decode($reponse, true);
    }
}
?>