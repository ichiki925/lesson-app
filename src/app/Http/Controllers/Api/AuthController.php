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

    /**
     * 新規登録
     * POST /api/register
     */
    public function register(Request $request)
    {
        // バリデーション
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:teachers,email',
            'password' => 'required|string|min:6|confirmed',
        ], [
            'name.required' => '名前は必須です',
            'email.required' => 'メールアドレスは必須です',
            'email.email' => '有効なメールアドレスを入力してください',
            'email.unique' => 'このメールアドレスは既に登録されています',
            'password.required' => 'パスワードは必須です',
            'password.min' => 'パスワードは6文字以上で入力してください',
            'password.confirmed' => 'パスワードが一致しません',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // ユーザー作成
        $teacher = Teacher::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // トークン生成
        $token = $teacher->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => '登録しました',
            'data' => [
                'teacher' => [
                    'id' => $teacher->id,
                    'name' => $teacher->name,
                    'email' => $teacher->email,
                ],
                'token' => $token,
            ],
        ], 201);
    }

    /**
     * パスワードリセットリクエスト
     * POST /api/forgot-password
     */
    public function forgotPassword(Request $request)
    {
        // バリデーション
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:teachers,email',
        ], [
            'email.required' => 'メールアドレスは必須です',
            'email.email' => '有効なメールアドレスを入力してください',
            'email.exists' => 'このメールアドレスは登録されていません',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // パスワードリセットトークンを生成
        $token = \Str::random(64);

        // トークンをデータベースに保存
        \DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'email' => $request->email,
                'token' => Hash::make($token),
                'created_at' => now(),
            ]
        );

        // リセットリンクをメールで送信
        $teacher = Teacher::where('email', $request->email)->first();
        \Mail::to($teacher->email)->send(new \App\Mail\PasswordResetLink($teacher, $token));

        return response()->json([
            'success' => true,
            'message' => 'パスワードリセット用のリンクをメールで送信しました',
        ], 200);
    }

    /**
     * パスワードリセット実行
     * POST /api/reset-password
     */
    public function resetPassword(Request $request)
    {
        // バリデーション
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:teachers,email',
            'token' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ], [
            'email.required' => 'メールアドレスは必須です',
            'email.email' => '有効なメールアドレスを入力してください',
            'email.exists' => 'このメールアドレスは登録されていません',
            'token.required' => 'トークンは必須です',
            'password.required' => 'パスワードは必須です',
            'password.min' => 'パスワードは6文字以上で入力してください',
            'password.confirmed' => 'パスワードが一致しません',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // トークンの確認
        $resetRecord = \DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$resetRecord || !Hash::check($request->token, $resetRecord->token)) {
            return response()->json([
                'success' => false,
                'message' => '無効なトークンです',
            ], 400);
        }

        // トークンの有効期限チェック（1時間）
        if (now()->diffInMinutes($resetRecord->created_at) > 60) {
            return response()->json([
                'success' => false,
                'message' => 'トークンの有効期限が切れています',
            ], 400);
        }

        // パスワードを更新
        $teacher = Teacher::where('email', $request->email)->first();
        $teacher->update([
            'password' => Hash::make($request->password),
        ]);

        // トークンを削除
        \DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'パスワードが正常にリセットされました',
        ], 200);
    }
}
