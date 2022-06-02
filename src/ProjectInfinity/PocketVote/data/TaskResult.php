<?php

namespace ProjectInfinity\PocketVote\data;

class TaskResult {

    private $votes, $errorData, $error, $meta;

    public function __construct($votes = [], $error = false, $errorData = []) {
        $this->error = $error;
        $this->errorData = $errorData;
        $this->votes = $votes;
    }

    public function hasError(): bool {
        return $this->error;
    }

    public function setError($error) {
        $this->error = $error;
    }

    public function setErrorData(array $errorData) {
        $this->errorData = $errorData;
    }

    public function getError() {
        return $this->errorData;
    }

    public function hasVotes(): bool {
        return count($this->votes) > 0;
    }

    public function setVotes($votes) {
        $this->votes = $votes;
    }

    public function getVotes(): array {
        return $this->votes;
    }

    public function getMeta() {
        return $this->meta;
    }

    public function setMeta($meta) {
        $this->meta = $meta;
    }
}