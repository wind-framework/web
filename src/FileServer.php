<?php

namespace Wind\Web;

use Wind\Base\Config;
use Wind\Web\Response;
use Workerman\Protocols\Http\Request;

class FileServer
{

    /**
     * 静态文件输出
     *
     * @param Config $config
     * @param Request $request
     * @param string $filename
     * @return void|Response
     */
    public static function sendStatic(Config $config, Request $request, $filename)
    {
        $config = $config->get('server.static_file');

        //路径中可能包含 ../ 等相对路径段（解码后还原），须确保最终路径仍位于 document_root 内，防止路径穿越
        $documentRoot = realpath($config['document_root']);

        //对URL编码的文件名进行解码，以支持中文等多字节字符的路径
        $path = realpath($config['document_root'].'/'.rawurldecode($filename));

        if ($documentRoot === false || $path === false || !str_starts_with($path, $documentRoot.DIRECTORY_SEPARATOR)) {
            return new Response(404);
        }

        if ($config['enable_negotiation_cache'] && is_file($path)) {
            if (!empty($ifModifiedSince = $request->header('if-modified-since'))) {
                $modifiedTime = date('D, d M Y H:i:s',  filemtime($path)) . ' ' . \date_default_timezone_get();
                // 文件未修改则返回304
                if ($modifiedTime === $ifModifiedSince) {
                    return new Response(304);
                }
            }
        }

        return (new Response())->withHeader('X-Workerman-Sendfile', $path);
    }

}
