<?php
namespace Webfan\OAuth\ResourceOwner;

class GenericResourceOwner {
    protected array $data;
    public function __construct(array $data) { $this->data = $data; }
    public function toArray(): array { return $this->data; }
    public function getId() { return $this->data['id'] ?? null; }
    public function getName() { return $this->data['name'] ?? null; }
    public function getNickname() { return $this->data['login'] ?? null; }
    public function getEmail() { return $this->data['email'] ?? null; }
}
