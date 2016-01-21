<?php
namespace Azine\EmailBundle\Tests;
//Author		: de77
//Website		: www.de77.com
//License		: MIT (http://en.wikipedia.org/wiki/MIT_License)
//Class desc	: http://de77.com/php-class-fast-find-text-string-in-files-recursively

//------------------------------------------------------------------------------
//          If you like this class- please leave a comment on my site, thanks!
//------------------------------------------------------------------------------

class FindInFileUtil
{
    private $text;
    private $textlen;
    private $results;

    public $caseSensitive = true;
    public $error;
    public $excludeMode = true;
    public $formats = array('.jpg', '.gif', '.avi');

    /**
     *
     * @param  string $dir
     * @param  string $text
     * @return array  of files with the search-text
     */
    public function find($dir, $text)
    {
        $this->textlen = strlen($text);
        $this->text = $text;

        if ($this->textlen > 4096) {
            $this->error = 'You cannot search for such long text. Limit is 4096 Bytes';

            return false;
        }

        $this->results = array();
        if (substr($dir, -1, 1) != '/') {
            $dir .= '/';
        }
        $this->scandir2($dir);

        return $this->results;
    }

    /**
     * @param string $file
     */
    private function findTxtt($file)
    {
        $ext = strrchr($file, '.');

        if ($this->excludeMode == true) {
            if (in_array($ext, $this->formats)) {
                return false;
            }
        } else {
            if (!in_array($ext, $this->formats)) {
                return false;
            }
        }

        if (filesize($file)<40960) {
            $data = file_get_contents($file);
            if ($this->strpos2($data, $this->text)) {
                return true;
            }

            return false;
        }
        $f = fopen($file, 'r');
        $currentPos = 0;
        $step = 40960;
        while (!feof($f)) {
            $data = fread($f, $step);
            if (!$data) {
                break;
            } elseif ($this->strpos2($data, $this->text)) {
                fclose($f);

                return true;
            }
            $currentPos = $currentPos + $step - $this->textlen;
            fseek($f, $currentPos, SEEK_SET);
        }
        fclose($f);

        return false;
    }

    /**
     * @param string $haystack
     * @param string $keyword
     */
    private function strpos2($haystack, $keyword)
    {
        if (!$this->caseSensitive) {
            return (mb_stripos($haystack, $keyword) !== false);
        }

        return (mb_strpos($haystack, $keyword) !== false);
    }

    /**
     * @param string $dir
     */
    private function scandir2($dir)
    {
        if (is_dir($dir)) {
            if ($dh = opendir($dir)) {
                while (($file = readdir($dh)) !== false) {
                    if ($file != '.' and $file != '..') {
                        if (is_dir($dir . $file)) {
                            $this->scandir2($dir . $file . '/');
                        } else {
                            if ($this->findTxtt($dir . $file)) {
                                $this->results[] = $dir . $file;
                            }
                        }
                    }
                }
            closedir($dh);
            }
        }
    }
}
