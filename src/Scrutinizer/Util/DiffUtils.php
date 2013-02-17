<?php

namespace Scrutinizer\Util;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

abstract class DiffUtils
{
    /**
     * Generates a DIFF.
     *
     * @param string $before
     * @param string $after
     * @return string
     */
    public static function generate($before, $after)
    {
        if ($before === $after) {
            return '';
        }

        $beforeTmpFile = tempnam(sys_get_temp_dir(), 'diff');
        file_put_contents($beforeTmpFile, $before);

        $afterTmpFile = tempnam(sys_get_temp_dir(), 'diff');
        file_put_contents($afterTmpFile, $after);

        $proc = new Process('git diff --no-index --exit-code -- '.escapeshellarg($beforeTmpFile).' '.escapeshellarg($afterTmpFile));
        $proc->run();

        @unlink($beforeTmpFile);
        @unlink($afterTmpFile);

        switch ($proc->getExitCode()) {
            // $before and $after were deemed identical. Should normally never
            // be reached as we already check for this above.
            case 0:
                return '';

            // The first four lines contain information about the files which were being compared.
            // We can safely trim them as we only generate the diff for one file.
            case 1:
                $output = explode("\n", $proc->getOutput());

                return implode("\n", array_slice($output, 4));

            default:
                throw new ProcessFailedException($proc);
        }
    }

    public static function reverseApply($newContent, $diff)
    {
        return self::apply($newContent, $diff, true);
    }

    public static function apply($original, $diff, $reverse = false)
    {
        if ('' === trim($diff)) {
            return $original;
        }

        // Diffs must end with a new line, otherwise the command throws an error.
        if ("\n" !== substr($diff, -1)) {
            $diff .= "\n";
        }

        $oFile = tempnam(sys_get_temp_dir(), 'diff-apply');
        file_put_contents($oFile, $original);

        $pFile = tempnam(sys_get_temp_dir(), 'diff-patch');
        file_put_contents($pFile, "--- a/".basename($oFile)."\n+++ b/".basename($oFile)."\n".$diff);

        $proc = new Process(sprintf('cd %s && git apply'.($reverse ? ' --reverse' : '').' --no-index --verbose %s', escapeshellarg(dirname($pFile)), escapeshellarg(basename($pFile))));
        if (0 !== $proc->run()) {
            throw new ProcessFailedException($proc);
        }

        $appliedContent = file_get_contents($oFile);
        unlink($oFile);
        unlink($pFile);

        return $appliedContent;
    }

    /**
     * Parser for Github's DIFF format.
     *
     * @param string $diff
     * @return array an array of chunks
     */
    public static function parse($diff)
    {
        if ('' === $diff) {
            return array();
        }

        $lines = explode("\n", $diff);
        $nbLines = count($lines);
        $curLine = 0;
        $chunks = array();

        while ($curLine < $nbLines) {
            $chunks[] = self::parseChunk($lines, $nbLines, $curLine);
        }

        return $chunks;
    }

    // This is more or less a port of https://github.com/mojombo/grit/blob/master/lib/grit/diff.rb
    public static function diff($repositoryPath, $baseSha, $headSha, array $paths = array())
    {
        $proc = new Process(sprintf('cd %s && git diff %s %s -- %s',
            escapeshellarg($repositoryPath),
            escapeshellarg($baseSha),
            escapeshellarg($headSha),
            implode(' ', array_map('escapeshellarg', $paths))));

        if (0 !== $proc->run()) {
            throw new ProcessFailedException($proc);
        }

        return self::parseDiffs($proc->getOutput());
    }

    public static function parseDiffs($diffOutput)
    {
        if (empty($diffOutput)) {
            return array();
        }

        $match = function($regex, $line) {
            if ( ! preg_match($regex, $line, $match)) {
                throw new \RuntimeException(sprintf('"%s" did not match "%s".', $regex, $line));
            }

            return $match;
        };

        $lines = explode("\n", $diffOutput);

        $diffs = array();

        while ($lines) {
            list(, $aPath, $bPath) = $match('#^diff --git a/(.+?) b/(.+)$#', array_shift($lines));

            $aMode = $bMode = null;
            if (0 === strpos('old mode', $lines[0])) {
                list(, $aMode) = $match('#^old mode (\d+)#', array_shift($lines));
                list(, $bMode) = $match('#^new mode (\d+)#', array_shift($lines));
            }

            if ( ! $lines || 0 === strpos($lines[0], 'diff --git')) {
                $diffs[] = array(
                    'a_path' => $aPath,
                    'b_path' => $bPath,
                    'a_sha' => null,
                    'b_sha' => null,
                    'a_mode' => $aMode,
                    'b_mode' => $bMode,
                    'is_new' => false,
                    'is_deleted' => false,
                    'is_renamed' => false,
                    'diff' => null,
                    'sim_index' => null,
                );

                continue;
            }

            $simIndex = null;
            $newFile = $deletedFile = $renamedFile = false;

            if (0 === strpos($lines[0], 'new file')) {
                list(, $bMode) = $match('#new file mode (.+)$#', array_shift($lines));
                $newFile = true;
            } elseif (0 === strpos($lines[0], 'deleted file')) {
                list(, $aMode) = $match('#deleted file mode (.+)$#', array_shift($lines));
                $deletedFile = true;
            } elseif (0 === strpos($lines[0], 'similarity index')) {
                list(, $simIndex) = $match('#similarity index (\d+)%#', array_shift($lines));
                $renamedFile = true;
                array_shift($lines); // Shift away the ``rename from/to ...`` lines.
            }

            $m = $match('#^index ([0-9A-Fa-f]+)\.\.([0-9A-Fa-f]+) ?(.+)?$#', array_shift($lines));
            $m[3] = isset($m[3]) ? trim($m[3]) : null;
            list(, $aSha, $bSha, $bMode) = $m;

            $binaryFile = false;
            $diffLines = array();
            while ($lines && 0 !== strpos($lines[0], 'diff --git')) {
                if (0 === strpos($lines[0], '+++ ') || 0 === strpos($lines[0], '--- ')) {
                    array_shift($lines);

                    continue;
                }

                if (0 === strpos($lines[0], 'Binary files ')) {
                    array_shift($lines);
                    $binaryFile = true;

                    continue;
                }

                $diffLines[] = array_shift($lines);
            }

            $diffs[] = array(
                'a_path' => $aPath,
                'b_path' => $bPath,
                'a_sha' => $aSha,
                'b_sha' => $bSha,
                'a_mode' => $aMode,
                'b_mode' => $bMode,
                'is_new' => $newFile,
                'is_deleted' => $deletedFile,
                'is_renamed' => $renamedFile,
                'diff' => $binaryFile ? null : implode("\n", $diffLines),
                'sim_index' => $simIndex,
            );
        }

        return $diffs;
    }

    private static function parseChunk(array $lines, $nbLines, &$curLine)
    {
        if (!preg_match('/^@@ \-([0-9]+)(,[0-9]+)? \+([0-9]+)(?:,([0-9]+))? @@/', $lines[$curLine], $match)) {
            throw new \RuntimeException("Invalid chunk header: ".$lines[$curLine]);
        }

        $chunk = array(
            'original_start_index' => (int) $match[1],
            'original_size' => empty($match[2]) ? 1 : (int) substr($match[2], 1),
            'new_start_index' => (int) $match[3],
            'new_size' => isset($match[4]) ? (int) $match[4] : 1,
            'diff' => array()
        );

        while ($nbLines > ++$curLine) {
            if ('' === $lines[$curLine]) {
                $curLine += 1;
                break;
            }

            if ('@' === $lines[$curLine][0]) {
                break;
            }

            if (' ' === $lines[$curLine][0]) {
                $type = 'common';
            } elseif ('-' === $lines[$curLine][0]) {
                $type = 'removed';
            } elseif ('+' === $lines[$curLine][0]) {
                $type = 'added';
            } elseif ('\\' === $lines[$curLine][0]) {
                continue;
            } else {
                throw new \RuntimeException(sprintf('Invalid line start "%s" of line "%s".', $lines[$curLine][0], $lines[$curLine]));
            }

            $chunk['diff'][] = array(
                'type' => $type,
                'content' => substr($lines[$curLine], 1),
            );
        }

        return $chunk;
    }

    final private function __construct() { }
}