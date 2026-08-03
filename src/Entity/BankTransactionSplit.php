<?php
namespace App\Entity;
use App\Repository\BankTransactionSplitRepository;
use App\Service\UlidGenerator;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BankTransactionSplitRepository::class)]
#[ORM\Index(columns: ['transaction_id'], name: 'IDX_BANK_SPLIT_TRANSACTION')]
class BankTransactionSplit
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id=null;
    #[ORM\Column(length:26,unique:true)] private string $publicId;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable:false,onDelete:'CASCADE')] private ?BankTransaction $transaction=null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable:false,onDelete:'CASCADE')] private ?User $user=null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable:true,onDelete:'SET NULL')] private ?BankCategory $category=null;
    #[ORM\Column(length:2048)] private string $encryptedLabel='';
    #[ORM\Column(type:Types::DECIMAL,precision:12,scale:2)] private string $amount='0.00';
    #[ORM\Column(type:Types::DATETIME_MUTABLE)] private \DateTimeInterface $createdAt;
    public function __construct(){ $this->publicId=UlidGenerator::generate(); $this->createdAt=new \DateTime(); }
    public function getId():?int{return $this->id;} public function getPublicId():string{return $this->publicId;}
    public function getTransaction():?BankTransaction{return $this->transaction;} public function setTransaction(?BankTransaction $v):static{$this->transaction=$v;return $this;}
    public function getUser():?User{return $this->user;} public function setUser(?User $v):static{$this->user=$v;return $this;}
    public function getCategory():?BankCategory{return $this->category;} public function setCategory(?BankCategory $v):static{$this->category=$v;return $this;}
    public function getEncryptedLabel():string{return $this->encryptedLabel;} public function setEncryptedLabel(string $v):static{$this->encryptedLabel=$v;return $this;}
    public function getAmount():float{return (float)$this->amount;} public function setAmount(float|string $v):static{$this->amount=number_format((float)$v,2,'.','');return $this;}
    public function getCreatedAt():\DateTimeInterface{return $this->createdAt;}
}
