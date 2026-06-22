<?php

declare(strict_types=1);

namespace SpecShaper\EncryptBundle\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\Persistence\Event\LifecycleEventArgs;

interface DoctrineEncryptListenerInterface
{
    public const ENCRYPTED_SUFFIX = '<ENC>';

    public function onFlush(OnFlushEventArgs $args): void;

    /** @param LifecycleEventArgs<EntityManagerInterface> $args */
    public function postUpdate(LifecycleEventArgs $args): void;

    /** @param LifecycleEventArgs<EntityManagerInterface> $args */
    public function postLoad(LifecycleEventArgs $args): void;
}
