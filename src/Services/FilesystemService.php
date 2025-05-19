<?php

namespace MWGuerra\InteractiveUpgrader\Services;

use Symfony\Component\Process\Process;
use GuzzleHttp\Client;

class FilesystemService
{
    /**
     * Check if a file exists
     */
    public function fileExists(string $filename): bool
    {
        return file_exists($filename);
    }

    /**
     * Get the contents of a file
     */
    public function getFileContents(string $filename): string
    {
        return file_get_contents($filename);
    }

    /**
     * Execute a command and return the output
     */
    public function executeCommand(array $command): string
    {
        $process = new Process($command);
        $process->run();
        return $process->getOutput();
    }

    /**
     * Execute a command and return the parsed JSON output
     */
    public function executeJsonCommand(array $command): array
    {
        $output = $this->executeCommand($command);
        return json_decode($output, true) ?: [];
    }

    /**
     * Make an HTTP GET request and return the parsed JSON response
     */
    public function getJson(string $url, array $options = []): array
    {
        $client = new Client($options);
        $response = $client->get($url);
        return json_decode($response->getBody(), true) ?: [];
    }
}