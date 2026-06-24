<?php

class SmtpSender {
    private $host;
    private $port;
    private $username;
    private $password;
    private $timeout = 30;
    private $debug = false;

    public function __construct($config) {
        $this->host = $config['host'];
        $this->port = $config['port'];
        $this->username = $config['username'];
        $this->password = $config['password'];
    }

    public function send($to, $subject, $body, $headers = '') {
        $host = $this->host;
        if ($this->port == 465) {
            $host = 'ssl://' . $host;
        }

        $socket = fsockopen($host, $this->port, $errno, $errstr, $this->timeout);
        if (!$socket) {
            throw new Exception("Connection failed: $errno $errstr");
        }

        $this->read($socket); // Greeting

        $this->cmd($socket, 'EHLO ' . gethostname());
        
        if ($this->port == 587) {
            $this->cmd($socket, 'STARTTLS');
            
            $cryptoMethod = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                $cryptoMethod = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
            }
            
            if (!stream_socket_enable_crypto($socket, true, $cryptoMethod)) {
                throw new Exception("Failed to start encryption (TLS)");
            }
            
            $this->cmd($socket, 'EHLO ' . gethostname());
        }
        
        if ($this->username && $this->password) {
            $this->cmd($socket, 'AUTH LOGIN');
            $this->cmd($socket, base64_encode($this->username));
            $this->cmd($socket, base64_encode($this->password));
        }

        $this->cmd($socket, 'MAIL FROM: <' . $this->username . '>');
        $this->cmd($socket, 'RCPT TO: <' . $to . '>');
        $this->cmd($socket, 'DATA');

        $content = "Subject: $subject\r\n";
        $content .= "To: $to\r\n";
        $content .= $headers . "\r\n\r\n";
        $content .= $body . "\r\n";
        $content .= ".";

        $this->cmd($socket, $content);
        $this->cmd($socket, 'QUIT');

        fclose($socket);
        return true;
    }

    private function cmd($socket, $cmd) {
        fputs($socket, $cmd . "\r\n");
        $response = $this->read($socket);
        // Decommenter pour debug
        // echo "CMD: $cmd\nRESP: $response\n";
        return $response;
    }

    private function read($socket) {
        $response = '';
        while ($str = fgets($socket, 515)) {
            $response .= $str;
            if (substr($str, 3, 1) == ' ') break;
        }
        return $response;
    }
}
