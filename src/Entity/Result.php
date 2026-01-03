<?php

namespace App\Entity;

use App\Repository\ResultRepository;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use JsonSerializable;

#[ORM\Entity(repositoryClass: ResultRepository::class)]
#[ORM\Table(name: 'results')]
#[Serializer\XmlRoot(name: 'result')]
#[Serializer\XmlNamespace(uri: 'http://www.w3.org/2005/Atom', prefix: 'atom')]
#[Serializer\AccessorOrder(order: 'custom', custom: [ 'id', 'result', 'time', 'userId' ])]
class Result implements JsonSerializable
{
    public final const string RESULT_ATTR = 'result';
    public final const string TIME_ATTR = 'time';

    #[ORM\Column(name: 'id', type: 'integer', nullable: false)]
    #[ORM\Id, ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[Serializer\XmlAttribute]
    private ?int $id = null;

    #[ORM\Column(name: 'result', type: 'integer', nullable: false)]
    #[Serializer\SerializedName(self::RESULT_ATTR)]
    #[Serializer\XmlElement(cdata: false)]
    private int $result = 0;

    #[ORM\Column(name: 'time', type: 'datetime', nullable: false)]
    #[Serializer\SerializedName(self::TIME_ATTR)]
    #[Serializer\XmlElement(cdata: false)]
    private \DateTimeInterface $time;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Serializer\Exclude] 
    private ?User $user = null;

    public function __construct()
    {
        $this->time = new \DateTime();
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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    #[Serializer\VirtualProperty]
    #[Serializer\SerializedName('userId')]
    public function getUserId(): ?int
    {
        return $this->user?->getId();
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            self::RESULT_ATTR => $this->getResult(),
            self::TIME_ATTR => $this->getTime()->format(DATE_ATOM),
            'userId' => $this->getUserId(),
        ];
    }
}
