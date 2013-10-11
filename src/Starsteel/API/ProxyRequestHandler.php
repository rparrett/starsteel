<?php
/**
 * Request Handler for HTTP API, for Starsteel Proxy
 */

namespace Starsteel\API;


class ProxyRequestHandler implements APIRequestHandler {
    var $data = array();
    var $conns;
    var $templ;

    function __construct(&$conns, $templ) {
        $this->conns = $conns;
        $this->templ = $templ;
    }

    function getConnectionStats() {
        $conns_data = array();

        // Get each proxy's extracted data
        foreach($this->conns as $conn) {
            if (!$conn->getRemoteAddress()) {
                // TODO: Fix
                continue;
            }

            $proxy = $conns[$conn];
            $conn_data = $proxy->getData();
            $conn_data['ip'] = $conn->getRemoteAddress();
            $conns_data[$proxy->getId()] = $conn_data;
        }

        return $conns_data;
    }

    public function handle(\React\Http\Request $request, $response) {
        $conns_data = $this->getConnectionStats();

        echo "API Request: ".$request->getPath()." ".$request->getQuery()."\n";

        // Routes

        if ($request->getPath() == '/json') {
            $response->writeHead(200, array('Content-Type' => 'application/json'));


            $data['uptime'] = time() - $data['started'];

            $response->end(json_encode(array('result'=>true, 'data'=>$data, 'conns'=>$conns_data)));
        } elseif ($request->getPath() == '/') {
            $response->writeHead(200, array('Content-Type' => 'text/html'));
            $stats = array(
                'strength', 'health', 'agility', 'intellect', 'willpower', 'charm'
            );
            $secondary_stats = array(
                'magicres', 'perception', 'stealth', 'spellcasting', 'tracking', 'thievery', 'traps', 'picklocks', 'martial_arts'
            );
            $templ_data = array('data'=>$conns_data, 'stats'=>$stats, 'secondary_stats'=>$secondary_stats);
            //var_dump($templ_data);
            $response->end($this->templ->render('characters.thtml', $templ_data));
        } else {
            $response->writeHead(404, array('Content-Type' => 'text/html'));
            $response->end('<html><body>Sorry, not found!</body></html>');
        }
    }
}