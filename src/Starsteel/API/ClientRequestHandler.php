<?php
/**
 * Request Handler for HTTP API, for Starsteel Client
 */

namespace Starsteel\API;


class ClientRequestHandler implements APIRequestHandler {
    var $data = array();
    var $client;
    var $templ;
    var $web_dir;

    function __construct(&$client, &$templ, $web_dir) {
        $this->client = $client;
        $this->templ = $templ;
        $this->web_dir = $web_dir;
    }

    function getConnectionStats() {
        $conns_data = array();

        // TODO

        return $conns_data;
    }

    public function handle(\React\Http\Request $request, $response) {
        $conns_data = $this->getConnectionStats();

        echo "API Request: ".$request->getPath()." ".$request->getQuery()."\n";

        // Routes

        if ($request->getPath() == '/json') {

            $response->writeHead(200, array('Content-Type' => 'application/json'));

            $response->end(json_encode(array('result'=>true, 'data'=>$this->client->serializable())));
        } elseif ($request->getPath() == '/stats') {
            $response->writeHead(200, array('Content-Type' => 'text/html'));
            $response->end($this->templ->render('stats.thtml', array('client' => $this->client)));
        } elseif ($request->getPath() == '/') {

            $response->writeHead(200, array('Content-Type' => 'text/html'));
            $response->end($this->templ->render('main.thtml', array('client' => $this->client)));
        } elseif ($request->getPath() == '/styles.css') {
            $this->serveFile($response, 'styles.css', 'text/css');
        } else {
            $this->notFound($response, $request->getPath());
        }
    }

    public function notFound(&$response, $file) {
        $response->writeHead(404, array('Content-Type' => 'text/html'));
        $response->end('<html><body>Sorry, '.$file.' not found!</body></html>');
    }

    public function serveFile(&$response, $path, $mime_type) {
        $file = $this->web_dir.'/'.$path;
        if (file_exists($file)) {
            // TODO: Mime type detection
            $response->writeHead(200, array('Content-Type' => $mime_type));
            $response->end(file_get_contents($file));
        } else {
            $this->notFound($response, $file);
        }
    }
}