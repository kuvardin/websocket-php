<?php

/**
 * This file is used for the tests, but can also serve as an example of a WebSocket\Server.
 * Run in console: php examples/echoserver.php
 *
 * Console options:
 *  --port <int> : The port to listen to, default 8000
 *  --timeout <int> : Timeout in seconds, default 200 seconds
 *  --debug : Output log data (if logger is available)
 */

namespace WebSocket;

require __DIR__ . '/../vendor/autoload.php';

error_reporting(-1);

echo "# Echo server! [phrity/websocket]\n";

// Server options specified or default
$options = array_merge([
    'port'  => 8000,
], getopt('', ['port:', 'ssl', 'timeout:', 'framesize:', 'debug']));

// Initiate server.
try {
    $server = new Server($options['port'], isset($options['ssl']));
    $server
        ->addMiddleware(new \WebSocket\Middleware\CloseHandler())
        ->addMiddleware(new \WebSocket\Middleware\PingResponder())
        ;

    // If debug mode and logger is available
    if (isset($options['debug']) && class_exists('WebSocket\Test\EchoLog')) {
        $server->setLogger(new \WebSocket\Test\EchoLog());
        echo "# Using logger\n";
    }
    if (isset($options['timeout'])) {
        $server->setTimeout($options['timeout']);
        echo "# Set timeout: {$options['timeout']}\n";
    }
    if (isset($options['framesize'])) {
        $server->setFrameSize($options['framesize']);
        echo "# Set frame size: {$options['framesize']}\n";
    }

    echo "# Listening on port {$server->getPort()}\n";
    $server->onConnect(function ($server, $connection, $handshake) {
        echo "> [{$connection->getRemoteName()}] Client connected {$handshake->getUri()}\n";
    })->onDisconnect(function ($server, $connection) {
        echo "> [{$connection->getRemoteName()}] Client disconnected\n";
    })->onText(function ($server, $connection, $message) {
        echo "> [{$connection->getRemoteName()}] Received [{$message->getOpcode()}] {$message->getContent()}\n";
        switch ($message->getContent()) {
            // Connection commands
            case '@close':
                echo "< [{$connection->getRemoteName()}] Sending Close\n";
                $connection->send(new \WebSocket\Message\Close());
                break;
            case '@ping':
                echo "< [{$connection->getRemoteName()}] Sending Ping\n";
                $connection->send(new \WebSocket\Message\Ping());
                break;
            case '@disconnect':
                echo "< [{$connection->getRemoteName()}] Disconnecting\n";
                $connection->disconnect();
                break;
            case '@info':
                echo "< [{$connection->getRemoteName()}] Connection info:\n";
                echo "  - Local:       {$connection->getName()}\n";
                echo "  - Remote:      {$connection->getRemoteName()}\n";
                echo "  - Request:     {$connection->getHandshakeRequest()->getUri()}\n";
                echo "  - Response:    {$connection->getHandshakeResponse()->getStatusCode()}\n";
                echo "  - Connected:   " . json_encode($connection->isConnected()) . "\n";
                echo "  - Readable:    " . json_encode($connection->isReadable()) . "\n";
                echo "  - Writable:    " . json_encode($connection->isWritable()) . "\n";
                echo "  - Timeout:     {$connection->getTimeout()}s\n";
                echo "  - Frame size:  {$connection->getFrameSize()}b\n";
                break;

            // Server commands
            case '@server-stop':
                echo "< [{$connection->getRemoteName()}] Stop server\n";
                $server->stop();
                break;
            case '@server-close':
                echo "< [{$connection->getRemoteName()}] Broadcast Close\n";
                $server->send(new \WebSocket\Message\Close());
                break;
            case '@server-ping':
                echo "< [{$connection->getRemoteName()}] Broadcast Ping\n";
                $server->send(new \WebSocket\Message\Ping());
                break;
            case '@server-disconnect':
                echo "< [{$connection->getRemoteName()}] Disconnecting server\n";
                $server->disconnect();
                break;
            case '@server-info':
                echo "< [{$connection->getRemoteName()}] Server info:\n";
                echo "  - Running:     " . json_encode($server->isRunning()) . "\n";
                echo "  - Connections: {$server->getConnectionCount()}\n";
                echo "  - Port:        {$server->getPort()}\n";
                echo "  - Scheme:      {$server->getScheme()}\n";
                echo "  - Timeout:     {$server->getTimeout()}s\n";
                echo "  - Frame size:  {$server->getFrameSize()}b\n";
                break;

            // Echo received message
            default:
                $connection->pushMessage($message); // Echo
        }
    })->onBinary(function ($server, $connection, $message) {
        echo "> [{$connection->getRemoteName()}] Received [{$message->getOpcode()}]\n";
        $connection->pushMessage($message); // Echo
    })->onPing(function ($server, $connection, $message) {
        echo "> [{$connection->getRemoteName()}] Received [{$message->getOpcode()}] {$message->getContent()}\n";
    })->onPong(function ($server, $connection, $message) {
        echo "> [{$connection->getRemoteName()}] Received [{$message->getOpcode()}] {$message->getContent()}\n";
    })->onClose(function ($server, $connection, $message) {
        echo "> [{$connection->getRemoteName()}] Received [{$message->getOpcode()}] "
            . "{$message->getCloseStatus()} {$message->getContent()}\n";
    })->onError(function ($server, $connection, $exception) {
        echo "> Error: {$exception->getMessage()}\n";
    })->onTick(function ($server) {
        echo "-\n";
    })->start();
} catch (Throwable $e) {
    echo "> ERROR: {$e->getMessage()}\n";
}
