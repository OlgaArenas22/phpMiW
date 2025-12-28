<?php

namespace App\Entity;

use App\Repository\ResultRepository;
use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;

#[ORM\Entity(repositoryClass: ResultRepository::class)]
#[ORM\Table(name: 'results')]
class Result implements JsonSerializable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'integer')]
    private int $result;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $time;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    public function __construct(User $user, int $result, ?\DateTimeInterface $time = null)
    {
        $this->user = $user;
        $this->result = $result;
        $this->time = $time ?? new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getResult(): int
    {
        return $this->result;
    }

    public function setResult(int $result): self
    {
        $this->result = $result;
        return $this;
    }

    public function getTime(): \DateTimeInterface
    {
        return $this->time;
    }

    public function setTime(\DateTimeInterface $time): self
    {
        $this->time = $time;
        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'result' => $this->getResult(),
            'time' => $this->getTime()->format(DATE_ATOM),
            'userId' => $this->getUser()->getId(),
        ];
    }
}
