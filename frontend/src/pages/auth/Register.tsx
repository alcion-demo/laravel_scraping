import { useState } from 'react';
import api from '../../api/api';
import { useAuth } from '../../contexts/AuthContext';
import { Link, useNavigate } from 'react-router-dom';
import './components/Auth.css'; // ★ スタイルをインポート

function Register() {
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [error, setError] = useState('');
  const navigate = useNavigate();

  const { refreshUser } = useAuth();

  const handleRegister = async () => {
    setError('');

    try {
      await api.get('/sanctum/csrf-cookie');

      await api.post('/register', {
        name,
        email,
        password,
        password_confirmation: passwordConfirmation,
      });

      await refreshUser();
      navigate('/dashboard');

    } catch (error: any) {
      setError(
        JSON.stringify(
          error.response?.data?.errors ??
          error.response?.data ??
          error.message,
          null,
          2
        )
      );
    }
  };

  return (
    <div className="auth-container">
      <div className="auth-card">
        <h2>新規アカウント登録</h2>

        {error && <div className="auth-error"><pre>{error}</pre></div>}

        <div className="input-group">
          <label>お名前</label>
          <input
            type="text"
            className="auth-input"
            placeholder="山田 太郎"
            value={name}
            onChange={(e) => setName(e.target.value)}
          />
        </div>

        <div className="input-group">
          <label>メールアドレス</label>
          <input
            type="email"
            className="auth-input"
            placeholder="example@email.com"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
          />
        </div>

        <div className="input-group">
          <label>パスワード</label>
          <input
            type="password"
            className="auth-input"
            placeholder="••••••••"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
          />
        </div>

        <div className="input-group">
          <label>パスワード（確認）</label>
          <input
            type="password"
            className="auth-input"
            placeholder="••••••••"
            value={passwordConfirmation}
            onChange={(e) => setPasswordConfirmation(e.target.value)}
          />
        </div>

        <button className="btn-auth" onClick={handleRegister}>
          登録する
        </button>

        <div className="auth-footer">
          <p>
            すでにアカウントをお持ちの方は <Link to="/login">ログイン</Link>
          </p>
        </div>
      </div>
    </div>
  );
}

export default Register;