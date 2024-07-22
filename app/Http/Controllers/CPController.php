<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCPRequest;
use App\Http\Requests\UpdateCPRequest;
use App\Models\CP;

class CPController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreCPRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreCPRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\CP  $cP
     * @return \Illuminate\Http\Response
     */
    public function show(CP $cP)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\CP  $cP
     * @return \Illuminate\Http\Response
     */
    public function edit(CP $cP)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateCPRequest  $request
     * @param  \App\Models\CP  $cP
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateCPRequest $request, CP $cP)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\CP  $cP
     * @return \Illuminate\Http\Response
     */
    public function destroy(CP $cP)
    {
        //
    }
}
