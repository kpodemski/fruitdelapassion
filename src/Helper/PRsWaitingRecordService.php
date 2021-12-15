<?php

declare(strict_types=1);

namespace App\Helper;

use App\Database\Entity\PRWaitingStat;
use DateTime;
use Doctrine\ORM\EntityManager;
use Exception;

class PRsWaitingRecordService
{
    /**
     * @var string
     */
    private string $githubToken;

    /**
     * @var EntityManager
     */
    private EntityManager $entityManager;

    /**
     * @param string $githubToken
     * @param EntityManager $entityManager
     */
    public function __construct(string $githubToken, EntityManager $entityManager)
    {
        $this->githubToken = $githubToken;
        $this->entityManager = $entityManager;
    }

    /**
     * @param bool $isDryRun
     *
     * @return string[]
     */
    public function recordAllPRsWaiting(bool $isDryRun): array
    {
        $baseUrl = 'https://api.github.com/search/issues?per_page=100';

        $logs = [];
        $map = [
            PRWaitingStat::PR_WAITING_FOR_REVIEW => 'q=org%3APrestaShop+is%3Apr+is%3Aopen+review%3Arequired+archived%3Afalse',
            PRWaitingStat::PR_WAITING_FOR_QA => 'q=org%3APrestaShop+is%3Apr+is%3Aopen+label%3A"Waiting+for+QA"+archived%3Afalse',
            PRWaitingStat::PR_WAITING_FOR_PM => 'q=org%3APrestaShop+is%3Apr+is%3Aopen+label%3A"Waiting+for+PM"+archived%3Afalse',
            PRWaitingStat::PR_WAITING_FOR_DEV => 'q=org%3APrestaShop+is%3Apr+is%3Aopen+label%3A"Waiting+for+dev"+archived%3Afalse+sort%3Aupdated-asc',
            PRWaitingStat::PR_WAITING_FOR_DEV_AND_QA => 'q=org%3APrestaShop+is%3Apr+is%3Aopen+archived%3Afalse+sort%3Aupdated-asc+label%3A"Waiting+for+dev"+label%3A"Waiting+for+QA"',
            PRWaitingStat::PR_Open => 'q=org%3APrestaShop+is%3Apr+is%3Aopen+archived%3Afalse+sort%3Aupdated-asc',
        ];

        foreach ($map as $type => $url) {
            $total = $this->getCount($baseUrl . '&' . $url);
            $statRecord = new PRwaitingStat($type, new DateTime(), $total);
            $logs[] = sprintf('Recorded %d PRs %s', $total, $type);
            if (!$isDryRun) {
                $this->entityManager->persist($statRecord);
            }
        }

        if (!$isDryRun) {
            $this->entityManager->flush();
        }

        return $logs;
    }

    /**
     * @param string $url
     *
     * @return int
     */
    private function getCount(string $url): int
    {
        $chObj = curl_init();
        curl_setopt($chObj, CURLOPT_URL, $url);
        curl_setopt($chObj, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($chObj, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($chObj, CURLOPT_VERBOSE, false);
        curl_setopt($chObj, CURLOPT_HTTPHEADER,
            array(
                'User-Agent: PHP Script',
                'Content-Type: application/json;charset=utf-8',
                'Authorization: bearer ' . $this->githubToken
            )
        );

        $data = json_decode(curl_exec($chObj), true);

        if (!isset($data['total_count'])) {
            throw new Exception('No total_count in JSON response');
        }

        return $data['total_count'];
    }
}
