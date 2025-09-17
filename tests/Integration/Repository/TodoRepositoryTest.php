<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\Todo;
use App\Entity\User;
use App\Repository\TodoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class TodoRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private TodoRepository $repo;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->repo = $this->em->getRepository(Todo::class);

        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $tool = new SchemaTool($this->em);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
        unset($this->em, $this->repo);
    }

    public function testFindByOwnerFiltersAndOrdersByIdDesc(): void
    {
        $ownerA = (new User())->setEmail('a@example.com')->setPassword('x');
        $ownerB = (new User())->setEmail('b@example.com')->setPassword('x');
        $this->em->persist($ownerA);
        $this->em->persist($ownerB);
        $this->em->flush();

        $t1 = (new Todo())->setOwner($ownerA)->setTask('t1')->setDueAt(new \DateTimeImmutable('tomorrow'));
        $this->em->persist($t1); $this->em->flush();

        $t2 = (new Todo())->setOwner($ownerA)->setTask('t2')->setDueAt(new \DateTimeImmutable('tomorrow'));
        $this->em->persist($t2); $this->em->flush();

        $t3 = (new Todo())->setOwner($ownerA)->setTask('t3')->setDueAt(new \DateTimeImmutable('tomorrow'));
        $this->em->persist($t3); $this->em->flush();

        $tb = (new Todo())->setOwner($ownerB)->setTask('other')->setDueAt(new \DateTimeImmutable('tomorrow'));
        $this->em->persist($tb); $this->em->flush();

        $results = $this->repo->findByOwner($ownerA);

        $this->assertCount(3, $results, 'Should return only owner A todos');
        $this->assertSame($ownerA, $results[0]->getOwner());
        $this->assertSame($ownerA, $results[1]->getOwner());
        $this->assertSame($ownerA, $results[2]->getOwner());

        $this->assertGreaterThan($results[1]->getId(), $results[0]->getId());
        $this->assertGreaterThan($results[2]->getId(), $results[1]->getId());
    }
}
