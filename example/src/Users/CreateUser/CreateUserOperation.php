<?php

declare(strict_types=1);

namespace Example\Users\CreateUser;

interface CreateUserOperation
{
    public function execute(CreateUserCommand $command): void;
}
