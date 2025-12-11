<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * ログイン
     */
    public function login(Request $request)
    {
        // バリデーション
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // ユーザー検索
        $teacher = Teacher::where('email', $request->email)->first();

        // パスワードチェック
        if (!$teacher || !Hash::check($request->password, $teacher->password)) {
            return response()->json([
                'success' => false,
                'message' => 'メールアドレスまたはパスワードが正しくありません',
            ], 401);
        }

        // 既存のトークンを削除
        $teacher->tokens()->delete();

        // 新しいトークンを生成
        $token = $teacher->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'ログインしました',
            'data' => [
                'teacher' => [
                    'id' => $teacher->id,
                    'name' => $teacher->name,
                    'email' => $teacher->email,
                ],
                'token' => $token,
            ],
        ], 200);
    }

    /**
     * ログアウト
     */
    public function logout(Request $request)
    {
        // 現在のトークンを削除
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'ログアウトしました',
        ], 200);
    }

    /**
     * ログインユーザー情報取得
     */
    public function me(Request $request)
    {
        $teacher = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'teacher' => [
                    'id' => $teacher->id,
                    'name' => $teacher->name,
                    'email' => $teacher->email,
                ],
            ],
        ], 200);
    }
}
