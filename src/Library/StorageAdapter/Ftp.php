<?php

declare(strict_types=1);

namespace App\Library\StorageAdapter;

use App\Abstracts\ContainerAbstracts;
use App\Interfaces\ContainerInterface;
use App\Interfaces\StorageInterface;
use Psr\Http\Message\StreamInterface;

/**
 * FTP/FTPS 适配器
 *
 * NOTE: FTPS 需要开启 openssl 扩展
 *
 * Class Ftp
 * @package App\Library\Storage\Adapter
 */
class Ftp extends ContainerAbstracts implements StorageInterface
{
    /**
     * FTP 连接 resource
     *
     * @var resource
     */
    protected $connection;

    /**
     * 存储路径
     *
     * @var string
     */
    protected $pathPrefix;

    /**
     * Ftp constructor.
     *
     * @param ContainerInterface $container
     */
    public function __construct($container)
    {
        parent::__construct($container);

        if (!extension_loaded('ftp')) {
            throw new \Exception('PHP extension FTP is not loaded.');
        }

        $this->setPathPrefix();

        [
            'storage_ftp_username' => $username,
            'storage_ftp_password' => $password,
            'storage_ftp_host' => $host,
            'storage_ftp_port' => $port,
            'storage_ftp_ssl' => $ssl,
            'storage_ftp_passive' => $passive,
        ] = $container->optionService->getMultiple();

        $this->connection = $ssl
            ? ftp_ssl_connect($host, (int)$port)
            : ftp_connect($host, (int)$port);

        if (!$this->connection) {
            throw new \Exception("Couldn't connect to FTP Server ${host}:${port}");
        }

        ftp_login($this->connection, $username, $password);
        ftp_pasv($this->connection, !!$passive);
    }

    /**
     * 设置文件存储路径
     */
    protected function setPathPrefix(): void
    {
        $prefix = $this->container->optionService->storage_ftp_root;

        if ($prefix && !in_array(substr($prefix, -1), ['/', '\\'])) {
            $prefix .= '/';
        }

        $this->pathPrefix = $prefix;
    }

    /**
     * 获取包含文件路径的文件存储地址
     *
     * @param  string $path
     * @return string
     */
    protected function applyPathPrefix(string $path): string
    {
        return $this->pathPrefix . ltrim($path, '\\/');
    }

    /**
     * 确保指定目录存在，若不存在，则创建指定目录
     *
     * @param string $root
     */
    protected function ensureDirectory(string $root): void
    {
        $pwd = ftp_pwd($this->connection);
        $parts = explode('/', $root);

        foreach ($parts as $part) {
            if (!$part) {
                continue;
            }

            if (!@ftp_chdir($this->connection, $part)) {
                ftp_mkdir($this->connection, $part);
                ftp_chdir($this->connection, $part);
            }
        }

        ftp_chdir($this->connection, $pwd);
    }

    /**
     * 写入文件
     *
     * @param  string          $path
     * @param  StreamInterface $stream
     * @return bool
     */
    public function write(string $path, StreamInterface $stream): bool
    {
        $location = $this->applyPathPrefix($path);
        $this->ensureDirectory(dirname($location));

        return ftp_put($this->connection, $location, $stream->getMetadata('uri'), FTP_BINARY);
    }

    /**
     * 删除文件
     *
     * @param  string $path
     * @return bool
     */
    public function delete(string $path): bool
    {
        $location = $this->applyPathPrefix($path);

        return @ftp_delete($this->connection, $location);
    }

    /**
     * 析构方法，断开 FTP 连接
     */
    public function __destruct()
    {
        @ftp_close($this->connection);
    }
}