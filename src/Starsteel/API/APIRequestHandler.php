<?php

namespace Starsteel\API;


interface APIRequestHandler {

    function getConnectionStats();
    function handle(\React\Http\Request $request, $response);
}