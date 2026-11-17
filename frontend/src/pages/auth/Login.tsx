import { useState } from 'react';
import api from '../../api/api';
import { useAuth } from '../../contexts/AuthContext';
import { Link } from 'react-router-dom';
import './components/Auth.css'; // ★ スタイルをインポート

function Login() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');

  const { refreshUser } = useAuth();

  const handleLogin = async () => {
    setError('');

    try {
      await api.get('/sanctum/csrf-cookie');

      await api.post('/login', {
        email,
        password,
      });

      await refreshUser();

    } catch (error: any) {
      setError(
        JSON.stringify(
          error.response?.data ?? error.message,
          null,
          2
        )
      );
    }
  };

  return (
    <div className="auth-container">
      <div className="auth-card">
        <h2>ログイン</h2>

        {error && <div className="auth-error"><pre>{error}</pre></div>}

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

        <button className="btn-auth" onClick={handleLogin}>
          ログイン
        </button>

        <div className="auth-footer">
          <p>
            アカウントをお持ちでない方は <Link to="/register">新規登録</Link>
          </p>
        </div>
      </div>
    </div>
  );
}

export default Login;