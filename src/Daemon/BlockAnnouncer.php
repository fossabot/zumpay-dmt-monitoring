<?php
/**
 * Copyright Serhii Borodai (c) 2017-2018.
 */

/**
 * Created by Serhii Borodai <clarifying@gmail.com>
 */
declare(strict_types=1);

namespace Peth\Daemon;


use Peth\Config\RedisConfig as RedisConfig;
use EthereumRPC\API\Eth;
use Psr\Log\LoggerInterface;
use Redis;
use Zend\Db\Adapter\AdapterInterface;


class BlockAnnouncer implements DaemonInterface, RedisInteractionInterface
{

    use RedisInteractionTrait;
    const DB_NAMESPACE = 'peth';

    /**
     * @var Eth
     */
    private $eth;

    /**
     * @var int
     */
    private $connectionTimeoutTreshhold;
    /**
     * @var int
     */
    private $announcePeriod;
    /**
     * @var \Redis
     */
    private $redis;
    /**
     * @var RedisConfig
     */
    private $redisConfig;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var AdapterInterface
     */
    private $adapter;

    /**
     * BlockAnoncer constructor.
     * @param LoggerInterface $logger
     * @param Eth $eth
     * @param Redis $redis
     * @param RedisConfig $redisConfig
     * @param AdapterInterface $adapter
     * @param int $announcePeriod
     * @param int $connectionTimeoutTreshhold
     */
    public function __construct(LoggerInterface $logger,
                                Eth $eth,
                                Redis $redis,
                                RedisConfig $redisConfig,
                                AdapterInterface $adapter,
                                $announcePeriod = 15,
                                $connectionTimeoutTreshhold = 600)
    {

        $this->eth = $eth;
        $this->connectionTimeoutTreshhold = $connectionTimeoutTreshhold;
        $this->announcePeriod = $announcePeriod;
        $this->redis = $redis;
        $this->redisConfig = $redisConfig;
        $this->logger = $logger;
        $this->adapter = $adapter;

        $this->connectRedis($this->redis, $this->redisConfig);
    }

    public function process()
    {
//        $connectionTimeOut = 0;
        while (true) {
//            while ($connectionTimeOut < $this->connectionTimeoutTreshhold) {
                try {
                    $counter = 0;
                    $this->redis->ping();
                    $ethLastBlock = (string) $this->eth->blockNumber();
                    //$announced = $this->redis->lIndex(self::class, 0);
                    //@todo move get/set last announced block to separate method
                    $announced = $this->redis->get(self::class . 'announced');
                    if (!$announced) {
                        $announced = $this->getAnnouncedFromDB();
                    }
                    $this->logger->info(sprintf('announced is %s, ETH last block is %s', $announced, $ethLastBlock));

                    while ($ethLastBlock > $announced) {
                        $announceBucket = ($ethLastBlock - $announced < 1000 ? $ethLastBlock : bcadd("1000" , $announced,0));

                        $this->logger->info(sprintf('bucket from %s to %s', $announced, $announceBucket));

                        $pushed = $this->redisLPush(self::class, range(bcadd($announced, "1", 0), $announceBucket));
                        if ($pushed === false) {
                            throw new \Exception(sprintf("Can't push announce bucket %s", $announceBucket));
                        }
                        $this->setAnnouncedToDB($announceBucket);
                        $this->logger->info(sprintf("announced %s blocks", bcsub($announceBucket, $announced,0)));

                        $announced = $announceBucket;
                        //@todo move get/set last announced block to separate method
                        $this->redis->set(self::class . 'announced', $announced);
                        $counter ++;
                    }

//                    $connectionTimeOut = 0;
                    $this->logger->info(sprintf('Done %s iterations', $counter));
                } catch (\RedisException $e) {
                    $this->connectRedis($this->redis, $this->redisConfig);
                } catch (\Exception $exception) {
                    $this->logger->error($exception->getMessage());
                }
//            }
            sleep($this->announcePeriod);
        }
    }

    /**
     * @return Eth
     */
    public function getEth(): Eth
    {
        return $this->eth;
    }

    /**
     * @return int
     */
    public function getConnectionTimeoutTreshhold(): int
    {
        return $this->connectionTimeoutTreshhold;
    }

    /**
     * @return int
     */
    public function getAnnouncePeriod(): int
    {
        return $this->announcePeriod;
    }

    /**
     * @return RedisConfig
     */
    public function getRedisConfig(): RedisConfig
    {
        return $this->redisConfig;
    }

    public function getRedis(): Redis
    {
        return $this->redis;
    }

    /**
     * @return string
     */
    protected function getAnnouncedFromDB(): string
    {
        /** @var \Zend\Db\Adapter\Driver\Pdo\Result $statement */
        $statement = $this->adapter->getDriver()->createStatement(
            sprintf('SELECT announced FROM peth WHERE namespace = "%s"', self::DB_NAMESPACE)
        )->execute();
        if ($statement->valid()) {
            $row = $statement->current();
            $announced = $row['announced'] ?? '0';
        } else {
            $announced = '0';
        }
        return $announced;
    }

    /**
     * @param $announceBucket
     * @return int
     */
    protected function setAnnouncedToDB($announceBucket): int
    {
        $result = $this->adapter->getDriver()
            ->createStatement('INSERT peth (announced, namespace) VALUES(:announced, :namespace) ON DUPLICATE KEY UPDATE announced = :announced')
            ->execute(['announced' => $announceBucket, 'namespace' => self::DB_NAMESPACE])
        ;
        return $result->getAffectedRows();
    }

}