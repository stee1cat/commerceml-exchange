<?php
/**
 * Copyright (c) 2017 Gennadiy Khatuntsev <e.steelcat@gmail.com>
 */

namespace stee1cat\CommerceMLExchange;

use stee1cat\CommerceMLExchange\Http\AuthData;

/**
 * Class Controller
 * @package stee1cat\CommerceMLExchange
 */
class Controller extends AbstractController {

    public function stageCheckauth() {
        $authData = $this->request->getAuthData();

        if ($this->checkAuth($authData)) {
            $this->success(session_name() . PHP_EOL . session_id());
        }
        else {
            $this->failure(sprintf('Access denied' . ($authData->getUsername() ? ' for %s' : ''), $authData->getUsername()));
        }
    }

    public function stageInit() {
        if ($this->config->isZipSupport()) {
            $response = 'zip=yes';
        }
        else {
            $response = 'zip=no';
        }

        $response .= PHP_EOL . sprintf('file_limit=%d', $this->config->getFileSizeLimit());

        $this->message($response);
        $this->logger->info('< SUCCESS ' . $response);

        if ($this->request->get('version')) {
            $_SESSION['version'] = $this->request->get('version');
        }
    }

    public function stageUpload() {
        if (!$this->request->get('filename')) {
            $this->failure('Empty filename');
        }

        if (!$this->validateFilename($this->request->get('filename'))) {
            $this->failure('Incorrect file name');
        }

        if (!$this->prepareUploadPath()) {
            $this->failure('Failed to prepare directory');
        }

        if ($this->writeFile()) {
            $this->success();
        }
    }

    public function stageImport() {
        if (!$this->request->get('filename')) {
            $this->failure('Empty filename');
        }

        if (!$this->validateFilename($this->request->get('filename'))) {
            $this->failure('Incorrect file name');
        }

        $filePath = $this->getFilePath($this->request->get('filename'));
        if (!file_exists($filePath)) {
            $this->failure('File not exists');
        }

        $this->success();
    }

    public function stageComplete() {
        $this->success();
    }

    protected function prepareUploadPath() {
        $mode = 0777;
        $uploadPath = rtrim($this->config->getUploadPath(), '/\\');

        if (!is_dir($uploadPath)) {
            return mkdir($uploadPath, $mode);
        }

        if (is_writable($uploadPath)) {
            return chmod($uploadPath, $mode);
        }

        return true;
    }

    /**
     * @param AuthData $authData
     *
     * @return boolean
     *
     */
    protected function checkAuth(AuthData $authData) {
        $usernameIsValid = $this->config->getUsername() === $authData->getUsername();
        $passwordIsValid = $this->config->getPassword() === $authData->getPassword();

        return $usernameIsValid && $passwordIsValid;
    }

    protected function getFilePath($filename) {
        return $this->config->getUploadPath() . DIRECTORY_SEPARATOR . basename($filename);
    }

    protected function writeFile() {
        $filePath = $this->getFilePath($this->request->get('filename'));

        $handle = fopen($filePath, 'ab');
        if (!$handle) {
            $this->failure('Error opening file');

            return false;
        }

        $data = file_get_contents('php://input');
        $result = fwrite($handle, $data);
        fclose($handle);

        $size = strlen($data);
        if ($result !== $size) {
            $this->failure('Wrong data size written');

            return false;
        }

        return true;
    }

    /**
     * @param string $filename
     *
     * @return bool
     */
    protected function validateFilename($filename) {
        return !!preg_match('/^[0-9a-zA-Z_\-.\/]+$/', $filename);
    }

}