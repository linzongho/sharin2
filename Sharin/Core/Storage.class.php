<?php
/**
 * Email: linzongho@gmail.com
 * Github: https://github.com/linzongho/Sharin
 * User: asus
 * Date: 8/22/16
 * Time: 10:42 AM
 */
namespace Sharin\Core;
use Sharin\Core;

/**
 * Class Storage
 * @method mixed read(string $filepath, string $file_encoding = null, bool $recursion = false) static 获取文件内容
 * @method int has(string $filepath) static 确定文件或者目录是否存在
 * @method int|bool mtime(string $filepath, int $mtime = null) static 返回文件内容上次的修改时间
 * @method int|false size(string $filepath) static 获取文件按大小
 * @method bool mkdir(string $dirpath,int $auth = 0766) static 创建文件夹
 * @method bool touch(string $filepath,int  $mtime = null,int  $atime = null) static 设定文件的访问和修改时间
 * @method bool chmod(string $filepath,int  $auth = 0755) static 修改文件权限
 * @method bool unlink(string $filepath,bool $recursion = false) static 删除文件,目录时必须保证该目录为空
 * @method bool write(string $filepath,string $content,string $write_encode = null,string $text_encode = 'UTF-8') static 将指定内容写入到文件中
 * @method bool append(string $filepath,string  $content,string $write_encode = null,string $text_encode = 'UTF-8') static 将指定内容追加到文件中
 * @package Sharin
 */
class Storage extends Core {

    const CONF_NAME = 'storage';
    const CONF_CONVENTION = [
        'DRIVER_DEFAULT_INDEX' => 0,
        'DRIVER_CLASS_LIST' => [
            'Sharin\\Core\\Storage\\File',
        ],
        'DRIVER_CONFIG_LIST' => [
            [
                'READ_LIMIT_ON'     => true,
                'WRITE_LIMIT_ON'    => true,
                'READABLE_SCOPE'    => SR_PATH_BASE,
                'WRITABLE_SCOPE'    => SR_PATH_RUNTIME,
            ],
        ],
    ];

    /**
     * 目录存在与否
     */
    const IS_DIR    = -1;
    const IS_FILE   = 1;
    const IS_EMPTY  = 0;

//-------------------------------- 特征方法，仅适用于文件系统的驱动 ----------------------------------------------------------------------//
    /**
     * 获取文件权限，以linux的格式显示
     * @static
     * @param string $file
     * @return string|false
     */
    public static function permission($file){
        if(is_readable($file)){
            $perms = fileperms($file);
            if (($perms & 0xC000) == 0xC000) {
                // Socket
                $info = 's';
            } elseif (($perms & 0xA000) == 0xA000) {
                // Symbolic Link
                $info = 'l';
            } elseif (($perms & 0x8000) == 0x8000) {
                // Regular
                $info = '-';
            } elseif (($perms & 0x6000) == 0x6000) {
                // Block special
                $info = 'b';
            } elseif (($perms & 0x4000) == 0x4000) {
                // Directory
                $info = 'd';
            } elseif (($perms & 0x2000) == 0x2000) {
                // Character special
                $info = 'c';
            } elseif (($perms & 0x1000) == 0x1000) {
                // FIFO pipe
                $info = 'p';
            } else {
                // Unknown
                $info = 'u';
            }

            // Owner
            $info .= (($perms & 0x0100) ? 'r' : '-');
            $info .= (($perms & 0x0080) ? 'w' : '-');
            $info .= (($perms & 0x0040) ?
                (($perms & 0x0800) ? 's' : 'x' ) :
                (($perms & 0x0800) ? 'S' : '-'));

            // Group
            $info .= (($perms & 0x0020) ? 'r' : '-');
            $info .= (($perms & 0x0010) ? 'w' : '-');
            $info .= (($perms & 0x0008) ?
                (($perms & 0x0400) ? 's' : 'x' ) :
                (($perms & 0x0400) ? 'S' : '-'));

            // Other
            $info .= (($perms & 0x0004) ? 'r' : '-');
            $info .= (($perms & 0x0002) ? 'w' : '-');
            $info .= (($perms & 0x0001) ?
                (($perms & 0x0200) ? 't' : 'x' ) :
                (($perms & 0x0200) ? 'T' : '-'));
            return $info;
        }else{
            return false;
        }
    }

    /**
     * 参数一是否是文件
     * @static
     * @param $file
     * @param bool $isfile
     * @return string
     */
    public static function perm($file,$isfile=true){
        $Mode = $isfile?fileperms($file):$file;
        $theMode = ' '.decoct($Mode);
        $theMode = substr($theMode,-4);
        $Owner = array();$Group=array();$World=array();
        if ($Mode &0x1000) $Type = 'p'; // FIFO pipe
        elseif ($Mode &0x2000) $Type = 'c'; // Character special
        elseif ($Mode &0x4000) $Type = 'd'; // Directory
        elseif ($Mode &0x6000) $Type = 'b'; // Block special
        elseif ($Mode &0x8000) $Type = '-'; // Regular
        elseif ($Mode &0xA000) $Type = 'l'; // Symbolic Link
        elseif ($Mode &0xC000) $Type = 's'; // Socket
        else $Type = 'u'; // UNKNOWN

        // Determine les permissions par Groupe
        $Owner['r'] = ($Mode &00400) ? 'r' : '-';
        $Owner['w'] = ($Mode &00200) ? 'w' : '-';
        $Owner['x'] = ($Mode &00100) ? 'x' : '-';
        $Group['r'] = ($Mode &00040) ? 'r' : '-';
        $Group['w'] = ($Mode &00020) ? 'w' : '-';
        $Group['e'] = ($Mode &00010) ? 'x' : '-';
        $World['r'] = ($Mode &00004) ? 'r' : '-';
        $World['w'] = ($Mode &00002) ? 'w' : '-';
        $World['e'] = ($Mode &00001) ? 'x' : '-';

        // Adjuste pour SUID, SGID et sticky bit
        if ($Mode &0x800) $Owner['e'] = ($Owner['e'] == 'x') ? 's' : 'S';
        if ($Mode &0x400) $Group['e'] = ($Group['e'] == 'x') ? 's' : 'S';
        if ($Mode &0x200) $World['e'] = ($World['e'] == 'x') ? 't' : 'T';
        $Mode = $Type.$Owner['r'].$Owner['w'].$Owner['x'].' '.
            $Group['r'].$Group['w'].$Group['e'].' '.
            $World['r'].$World['w'].$World['e'];
        return $Mode.' ('.$theMode.') ';
    }
}