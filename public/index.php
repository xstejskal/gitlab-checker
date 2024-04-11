<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;
use Tracy\Debugger;
use Nette\Utils;

// Debugger::enable(Debugger::Development);

class Members
{
    private Client $client;

    static array $roles = [
        0 => 'No access',
        5 => 'Minimal access',
        10 => 'Guest',
        20 => 'Reporter',
        30 => 'Developer',
        40 => 'Maintainer',
        50 => 'Owner',
    ];

    private array $members = [];

    public function __construct(
        private int    $topLevelGroupId,
        private string $token,
    )
    {
        $this->client = new Client([
            // Base URI is used with relative requests
            'base_uri' => 'https://gitlab.com/api/v4/',
            // You can set any number of default request options.
            'timeout' => 2.0,
        ]);
    }

    private function callRequest(string $path, int $page = 1): array|stdClass
    {
        $perPage = 20;

        try {
            // https://docs.gitlab.com/ee/api/rest/index.html#pagination
            $response = $this->client->request('GET', $path, [
                'headers' => [
                    'PRIVATE-TOKEN' => $this->token,
                ],
                'query' => [
                    'per_page' => $perPage,
                    'page' => $page,
                ],
            ]);
        } catch (GuzzleHttp\Exception\GuzzleException $e) {
            exit($e->getMessage());
        }

        if ($response->getStatusCode() !== 200) {
            exit('Error: ' . $response->getStatusCode() . ' ' . $response->getReasonPhrase());
        }

        try {
            $contents = Utils\Json::decode($response->getBody()->getContents());

            if (is_array($contents) && count($contents) === $perPage) {
                $contents = array_merge($contents, $this->callRequest($path, $page + 1));
            }

            return $contents;

        } catch (Utils\JsonException $e) {
            exit($e->getMessage());
        }
    }

    private function getGroups(): void
    {
        $group = $this->callRequest("groups/{$this->topLevelGroupId}");
        // dump($group);
        $this->getProjects($group);
        $this->getMembers($group);

        foreach ($this->callRequest("groups/{$this->topLevelGroupId}/descendant_groups") as $group) {
            // dump($group);
            $this->getProjects($group);
            $this->getMembers($group);
        }
    }

    private function getProjects(object $group): void
    {
        foreach ($this->callRequest("groups/{$group->id}/projects") as $project) {
            // dump($project);
            $this->getMembers($project);
        }
    }

    private function getMembers(object $resource): void
    {
        if (property_exists($resource, 'full_path')) {
            $path = 'groups';
            $property = 'full_path';
        } elseif (property_exists($resource, 'path_with_namespace')) {
            $path = 'projects';
            $property = 'path_with_namespace';
        } else {
            return;
        }

        foreach ($this->callRequest("{$path}/{$resource->id}/members") as $member) {

            if (!key_exists($member->id, $this->members)) {
                $this->members[$member->id] = [
                    'name' => "$member->name (@{$member->username})",
                    'groups' => [],
                    'projects' => [],
                ];
            }

            $role = self::$roles[$member->access_level] ?? 'Unknown role';
            $this->members[$member->id][$path][] = $resource->{$property} . ' (' . $role . ')';
        }
    }

    function printMembers(): void
    {
        $this->getGroups();
        // dump($this->members);

        header('Content-Type: text/plain; charset=utf-8');

        foreach ($this->members as $member) {
            echo $member['name'] . PHP_EOL;
            echo 'Groups: [' . implode(', ', $member['groups']) . ']' . PHP_EOL;
            echo 'Projects: [' . implode(', ', $member['projects']) . ']' . PHP_EOL . PHP_EOL;
        }

        echo 'Total members: ' . count($this->members);
    }

}

if (PHP_SAPI === 'cli') {
    $topLevelGroupId = !empty($argv[1]) ? (int) $argv[1] : exit('Missing argument "id" of top level group');
} else {
    $topLevelGroupId = !empty($_GET['id']) ? (int) $_GET['id'] : exit('Missing query parameter "id" of top level group');
}

$token = getenv('GITLAB_TOKEN') ?: exit('Missing ENV variable GITLAB_TOKEN with GitLab access token');

$members = new Members($topLevelGroupId, $token);
$members->printMembers();




