<?php
/**
 * Created by JetBrains PhpStorm.
 * User: matt
 */

namespace Starsteel;

use Evenement\EventEmitter;
use React\SocketClient\Connector;

require_once __DIR__ . '/../funcs.php';

class Proxy extends EventEmitter {

    var $conn;
    var $mud_port;
    var $mud_ip;
    var $server;
    var $addr;

    private $id;
    private $data;

    function __construct($client_id, $client, $mud_ip, $mud_port = 23, $connector, $log)
    {
        $this->addr = $client->getRemoteAddress();
        $this->id = $client_id;
        $this->data = array('stats'=>array());
        $this->conn = $client;
        $this->mud_ip = $mud_ip;
        $this->mud_port = $mud_port;
        $this->log = $log;
        $this->analyzer = new Analyzer();

        // Establish a connection to the mud.

        $that = $this;
        $serverConn = $connector->create($mud_ip, $mud_port)->then(function($stream) use (&$that) {

            echo "Connected to remote {$that->mud_ip}:{$that->mud_port}\n";

            // Capture stream
            $that->server = $stream;

            // Data incoming from server
            $stream->on('data', array($that, 'onServerData'));
            $stream->on('end', array($that, 'onServerEnd'));

            // Handle incoming client data
            $that->conn->on('data', array($that, 'onClientData'));
            $that->conn->on('end', array ($that, 'onClientEnd'));

        }, function() { echo "ERROR\n"; });

    }

    function getId() {
        return $this->id;
    }

    function getData() {
        return $this->data;
    }

    function log_line($line) {

        $time = date("Y-m-d H:i:s");

        if ($this->log->isWritable()) {
            $this->log->write($time.' '.$this->addr.' '.$line."\n");
        } else {
            echo $time.' '.$line."\n";
        }
    }

    function onServerEnd() {
        echo "Lost server connection\n";
        $this->conn->close();
    }

    function onClientEnd() {
        $this->log_line("Client connection lost. Closing server connection.");

        // Todo: Is this how this should work?
        // Would be neat to be able to reconnect within a certain timeframe.

        $this->server->close(); // End?

        $this->emit('end', array($this));
    }

    function onClientData($data) {

        $this->log_line('C: '.$data."\n");

        if (null !== $this->server) {
            // Pass through to the server
            $this->server->write($data);
        }
    }

    function onServerData($data) {

        // Do stuff with the data, possibly modify
        $lines = explode("\r\n", $data);

        if (!isset($this->data['stats']))
            $this->data['stats'] = array();

        // Todo: Invalid UTF-8 sequence
        foreach($lines as $i => $line) {
            if ($line == '')
                continue;

            $stripped_line = Util::strip_ansi($line);

            if ($stripped_line != '') {
                $this->log_line('S: '.$stripped_line);
            }


            $this->analyzer->extract_stats($stripped_line, $this->data['stats']);

            if (isset($this->data['stats']['hits']) && $this->data['stats']['hits']['current'] <= 0) {
                echo "BAILING!!\n";
                $this->log_line('BAILING');
                $this->server->close();

                $this->emit('end', array($this));
            }

            if (false !== strpos(trim($stripped_line), "you can't see anything")) {
                $this->server->write("star\r\n");
            }
        }

        // Emit events, etc.

        // Pass through back to the client
        $this->conn->write($data);
    }
}
