<?php
namespace App\Http\Controllers\Network;
use App\Domain\Network\Map\NetworkMapRegistry;
use App\Http\Controllers\Controller;
final class NetworkLaunchpadController extends Controller { public function __invoke(NetworkMapRegistry $registry) { return view('network.launchpad',$registry->viewData()); } }
