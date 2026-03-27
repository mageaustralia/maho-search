<?php

declare(strict_types=1);

namespace Maho\Search\Lucene\Storage\Directory;

/**
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (https://www.zend.com)
 * @copyright  Copyright (c) 2026 Mage Australia Pty Ltd
 * @license    https://opensource.org/licenses/BSD-3-Clause  BSD 3-Clause License
 * @category   Maho
 * @package    Maho_Search_Lucene
 * @subpackage Storage
 */

/** \Maho\Search\Lucene\Storage\Directory */

/**
 * FileSystem implementation of Directory abstraction.
 *
 * @category   Maho
 * @package    Maho_Search_Lucene
 * @subpackage Storage
 */
class Filesystem extends \Maho\Search\Lucene\Storage\Directory
{
    /**
     * Filesystem path to the directory
     *
     * @var string
     */
    protected $_dirPath = null;

    /**
     * Cache for \Maho\Search\Lucene\Storage\File\Filesystem objects
     * Array: filename => \Maho\Search\Lucene\Storage\File object
     *
     * @var array
     * @throws \Maho\Search\Lucene\Exception
     */
    protected $_fileHandlers;

    /**
     * Default file permissions
     *
     * @var integer
     */
    protected static $_defaultFilePermissions = 0666;

    /**
     * Get default file permissions
     *
     * @return integer
     */
    public static function getDefaultFilePermissions()
    {
        return self::$_defaultFilePermissions;
    }

    /**
     * Set default file permissions
     *
     * @param integer $mode
     */
    public static function setDefaultFilePermissions($mode)
    {
        self::$_defaultFilePermissions = $mode;
    }

    /**
     * Utility function to recursive directory creation
     *
     * @param string $dir
     * @param integer $mode
     * @param boolean $recursive
     * @return boolean
     */

    public static function mkdirs($dir, $mode = 0775, $recursive = true)
    {
        $mode = $mode & ~0002;

        if (($dir === null) || $dir === '') {
            return false;
        }
        if (is_dir($dir) || $dir === '/') {
            return true;
        }
        if (self::mkdirs(dirname($dir), $mode, $recursive)) {
            return mkdir($dir, $mode);
        }
        return false;
    }

    /**
     * Object constructor
     * Checks if $path is a directory or tries to create it.
     *
     * @param string $path
     * @throws \Maho\Search\Lucene\Exception
     */
    public function __construct($path)
    {
        if (!is_dir($path)) {
            if (file_exists($path)) {
                throw new \Maho\Search\Lucene\Exception('Path exists, but it\'s not a directory');
            } else {
                if (!self::mkdirs($path)) {
                    throw new \Maho\Search\Lucene\Exception("Can't create directory '$path'.");
                }
            }
        }
        $this->_dirPath = $path;
        $this->_fileHandlers = array();
    }

    /**
     * Closes the store.
     *
     * @return void
     */
    public function close()
    {
        foreach ($this->_fileHandlers as $fileObject) {
            $fileObject->close();
        }

        $this->_fileHandlers = array();
    }

    /**
     * Returns an array of strings, one for each file in the directory.
     *
     * @return array
     */
    public function fileList()
    {
        $result = array();

        $dirContent = opendir( $this->_dirPath );
        while (($file = readdir($dirContent)) !== false) {
            if (($file == '..')||($file == '.'))   continue;

            if( !is_dir($this->_dirPath . '/' . $file) ) {
                $result[] = $file;
            }
        }
        closedir($dirContent);

        return $result;
    }

    /**
     * Creates a new, empty file in the directory with the given $filename.
     *
     * @param string $filename
     * @return \Maho\Search\Lucene\Storage\File
     * @throws \Maho\Search\Lucene\Exception
     */
    public function createFile($filename)
    {
        if (isset($this->_fileHandlers[$filename])) {
            $this->_fileHandlers[$filename]->close();
        }
        unset($this->_fileHandlers[$filename]);
        $this->_fileHandlers[$filename] = new \Maho\Search\Lucene\Storage\File\Filesystem($this->_dirPath . '/' . $filename, 'w+b');

        // Set file permissions, but don't care about any possible failures, since file may be already
        // created by anther user which has to care about right permissions
        @chmod($this->_dirPath . '/' . $filename, self::$_defaultFilePermissions);

        return $this->_fileHandlers[$filename];
    }

    /**
     * Removes an existing $filename in the directory.
     *
     * @param string $filename
     * @return void
     * @throws \Maho\Search\Lucene\Exception
     */
    public function deleteFile($filename)
    {
        if (isset($this->_fileHandlers[$filename])) {
            $this->_fileHandlers[$filename]->close();
        }
        unset($this->_fileHandlers[$filename]);

        error_clear_last();
        if (!@unlink($this->_dirPath . '/' . $filename)) {
            $err = error_get_last();
            $phpErrormsg = isset($err['message'][0]) ? $err['message'] : null;
            throw new \Maho\Search\Lucene\Exception('Can\'t delete file: ' . $phpErrormsg);
        }
    }

    /**
     * Purge file if it's cached by directory object
     *
     * Method is used to prevent 'too many open files' error
     *
     * @param string $filename
     * @return void
     */
    public function purgeFile($filename)
    {
        if (isset($this->_fileHandlers[$filename])) {
            $this->_fileHandlers[$filename]->close();
        }
        unset($this->_fileHandlers[$filename]);
    }

    /**
     * Returns true if a file with the given $filename exists.
     *
     * @param string $filename
     * @return boolean
     */
    public function fileExists($filename)
    {
        return isset($this->_fileHandlers[$filename]) ||
               file_exists($this->_dirPath . '/' . $filename);
    }

    /**
     * Returns the length of a $filename in the directory.
     *
     * @param string $filename
     * @return integer
     */
    public function fileLength($filename)
    {
        if (isset( $this->_fileHandlers[$filename] )) {
            return $this->_fileHandlers[$filename]->size();
        }
        return filesize($this->_dirPath .'/'. $filename);
    }

    /**
     * Returns the UNIX timestamp $filename was last modified.
     *
     * @param string $filename
     * @return integer
     */
    public function fileModified($filename)
    {
        return filemtime($this->_dirPath .'/'. $filename);
    }

    /**
     * Renames an existing file in the directory.
     *
     * @param string $from
     * @param string $to
     * @return void
     * @throws \Maho\Search\Lucene\Exception
     */
    public function renameFile($from, $to)
    {
        if (isset($this->_fileHandlers[$from])) {
            $this->_fileHandlers[$from]->close();
        }
        unset($this->_fileHandlers[$from]);

        if (isset($this->_fileHandlers[$to])) {
            $this->_fileHandlers[$to]->close();
        }
        unset($this->_fileHandlers[$to]);

        if (file_exists($this->_dirPath . '/' . $to)) {
            if (!unlink($this->_dirPath . '/' . $to)) {
                throw new \Maho\Search\Lucene\Exception('Delete operation failed');
            }
        }

        $success = @rename($this->_dirPath . '/' . $from, $this->_dirPath . '/' . $to);
        if (!$success) {
            $err = error_get_last();
            $phpErrormsg = $err['message'];
            throw new \Maho\Search\Lucene\Exception($phpErrormsg);
        }

        return $success;
    }

    /**
     * Sets the modified time of $filename to now.
     *
     * @param string $filename
     * @return void
     */
    public function touchFile($filename)
    {
        return touch($this->_dirPath .'/'. $filename);
    }

    /**
     * Returns a \Maho\Search\Lucene\Storage\File object for a given $filename in the directory.
     *
     * If $shareHandler option is true, then file handler can be shared between File Object
     * requests. It speed-ups performance, but makes problems with file position.
     * Shared handler are good for short atomic requests.
     * Non-shared handlers are useful for stream file reading (especial for compound files).
     *
     * @param string $filename
     * @param boolean $shareHandler
     * @return \Maho\Search\Lucene\Storage\File
     */
    public function getFileObject($filename, $shareHandler = true)
    {
        $fullFilename = $this->_dirPath . '/' . $filename;

        if (!$shareHandler) {
            return new \Maho\Search\Lucene\Storage\File\Filesystem($fullFilename);
        }

        if (isset( $this->_fileHandlers[$filename] )) {
            $this->_fileHandlers[$filename]->seek(0);
            return $this->_fileHandlers[$filename];
        }

        $this->_fileHandlers[$filename] = new \Maho\Search\Lucene\Storage\File\Filesystem($fullFilename);
        return $this->_fileHandlers[$filename];
    }
}
