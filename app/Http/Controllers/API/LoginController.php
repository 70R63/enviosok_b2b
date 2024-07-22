<?php

namespace App\Http\Controllers\API;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\API\ApiController;
use Carbon\Carbon;

use Log;

class LoginController extends ApiController
{
    public function login(Request $request)
    {
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);

        $xApiKey = $request->header("x-api-key");
        $corporativo = $request->header("corporativo");
        
        $env = \Dotenv\Dotenv::createArrayBacked(base_path())->load();
        $corporativoCadena = isset($env[$corporativo]) ? $env[$corporativo] : "sin corporativo";
        $minutos = $request->minutos;

        $empresa_id = explode('_',$corporativoCadena)[0];
        Log::debug($corporativoCadena);
        Log::debug($empresa_id);
        Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
        if ( ($minutos > 10080 )) {
            
            return $this->sendError('Time exceeded.', ['error'=>'El valor maximo en minutos es 10080'], 409);
        } 

        if ($minutos <1) {
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
            $minutos= 1440;
        }

        
        if ( !($xApiKey === md5($corporativoCadena) )) {
            
            return $this->sendError('Unauthorized.', ['error'=>'Corporativo no Autorizado'], 403);
        }
  
        if(Auth::attempt(['email' => $request->email, 'password' => $request->password])) { 
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
            $data = $request->all();

            Log::debug(print_r( $request->all(),true));
            
            $user = Auth::user(); 

            Log::debug($user);
            if ( $user->empresa_id != $empresa_id ) {
            
                return $this->sendError('Unauthorized.', ['error'=>'Incongruencia Operativa'], 403);
            }
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
            $token = $user->createToken($request->email,array(),Carbon::now()->addMinutes( $minutos ));

            $response['token'] = $token->plainTextToken; 
            $response['name'] =  $user->name;
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);


            //Log::debug(print_r( $token->accessToken,true));

            $response['expires_at'] =  $token->accessToken->expires_at->toDateTimeString();

            return $this->successResponse('User successfully logged-in.', $response);
        } 
        else { 
            Log::info(__CLASS__." ".__FUNCTION__." ".__LINE__);
            return $this->sendError('Unauthorized.', ['error'=>'Usuario o Password invalidos'], 403);
        } 
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'email' => 'required|email|unique:users,email',
            'password' => 'required',
            'confirm_password' => 'required|same:password',
           
        ]);
   
        if($validator->fails()){
        	return $this->sendError('Validation error.', $validator->errors(), 400);
        }
   
        $data = $request->all();
        $data['password'] = bcrypt($data['password']);
        $data['empresa_id']=1;
        $user = User::create($data);

        $response['token'] =  $user->createToken($request->email)->plainTextToken;
        $response['name'] =  $user->name;
   
        return $this->successResponse('User created successfully.', $response);
    }

    public function logout() 
    {
        auth()->user()->currentAccessToken()->delete();

        return $this->successResponse('Logout successfully.');
    }
}
