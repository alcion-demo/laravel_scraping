import { useState } from 'react';
import api from '../api/api';
import { useAuth } from '../contexts/AuthContext';
import './Dashboard.css';

type Item = {
  title?: string;
  price?: string;
  image?: string;
  url?: string;
  [key: string]: any;
};

export default function Dashboard() {
  const { user, logout } = useAuth();

  const [loading, setLoading] = useState(false);
  const [selectedCategory, setSelectedCategory] = useState<string>(
    'https://www.zojirushi.co.jp/syohin/assets/cms/json/pot_kettle/pot_kettle.json'
  );
  const [items, setItems] = useState<Item[]>([]);
  const [errorMessage, setErrorMessage] = useState('');

  // ページネーション用のステート
  const [currentPage, setCurrentPage] = useState(1);
  const itemsPerPage = 20; // 1ページあたりの表示件数

  const categories = [
    { label: '象印：電気ケトル', url: 'https://www.zojirushi.co.jp/syohin/assets/cms/json/pot_kettle/pot_kettle.json' },
    { label: 'タイガー：電気ケトル', url: 'https://www.tiger-corporation.com/wp-json/api/v1/product?category%5B0%5D=electric-kettles&region=jpn&detailed=true' },
    { label: 'タイガー：ポット（電気まほうびん）', url: 'https://www.tiger-corporation.com/wp-json/api/v1/product?category%5B0%5D=water-heaters&region=jpn&detailed=true' },
    { label: 'Zenn', url: 'https://zenn.dev/' },
  ];

  const handleScraping = async () => {
    setLoading(true);
    setErrorMessage('');
    setItems([]);

    try {
      const response = await api.post('/api/scraping', {
        urls: [selectedCategory],
      });

      const responseItems = response.data.items || response.data.result?.items;
      const fetchedItems = Array.isArray(responseItems) ? responseItems : [];
      setItems(fetchedItems);
      setCurrentPage(1); // 取得し直したときは1ページ目に戻す

    } catch (error: any) {
      setErrorMessage(
        JSON.stringify(error.response?.data ?? error.message, null, 2)
      );
    } finally {
      setLoading(false);
    }
  };

  // CSVダウンロード処理
  // CSVダウンロード処理（ファイル名に選択中のカテゴリ名を含める）
  const handleDownloadCsv = () => {
    if (items.length === 0) return;

    // 現在選択されているカテゴリのラベルを取得してファイル名用に整形
    const currentCategory = categories.find(cat => cat.url === selectedCategory);
    let fileNamePrefix = 'scraping_data';
    if (currentCategory) {
      // コロンやスペースをアンダースコアに置き換えてファイル名として安全にする
      fileNamePrefix = currentCategory.label.replace(/[:：\s]/g, '_');
    }

    // CSVのヘッダー行を作成
    const headers = ['URL', 'Title', 'Price/Likes', 'Published At', 'Author'];

    // 各行のデータを作成
    const rows = items.map(item => [
      `"${item.url || ''}"`,
      `"${(item.title || '').replace(/"/g, '""')}"`,
      `"${item.likes !== undefined ? `${item.likes} likes` : (item.price || '')}"`,
      `"${item.published_at || ''}"`,
      `"${(item.author || '').replace(/"/g, '""')}"`
    ]);

    // CSV文字列の組み立て（BOM付与）
    const csvContent = '\uFEFF' + [headers.join(','), ...rows.map(e => e.join(','))].join('\n');

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');

    link.setAttribute('href', url);
    // ファイル名にカテゴリ名と日付を分かりやすく含める（例: 象印_電気ケトル_2026-11-17.csv）
    link.setAttribute('download', `${fileNamePrefix}_${new Date().toISOString().slice(0, 10)}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  return (
    <div className="dashboard-container">
      <header className="dashboard-header">
        <div className="user-info">
          <h2>データ収集ダッシュボード</h2>
          <p>ログイン中: <strong>{user?.name}</strong> ({user?.email})</p>
        </div>
        <button className="btn btn-secondary" onClick={logout}>
          ログアウト
        </button>
      </header>

      <section className="control-panel">
        <h3>取得対象の選択</h3>
        <div className="selector-group">
          <select
            className="select-box"
            value={selectedCategory}
            onChange={(e) => setSelectedCategory(e.target.value)}
          >
            {categories.map((cat, idx) => (
              <option key={idx} value={cat.url}>
                {cat.label}
              </option>
            ))}
          </select>

          <button
            className="btn btn-primary"
            onClick={handleScraping}
            disabled={loading}
          >
            {loading ? '収集中...' : '一括取得を実行'}
          </button>
        </div>
      </section>

      {errorMessage && (
        <div className="alert alert-error">
          <pre>{errorMessage}</pre>
        </div>
      )}

      <section className="preview-section">
        <div className="section-header">
          <h3>取得プレビュー ({items.length} 件)</h3>
          {items.length > 0 && (
            <button className="btn btn-success" onClick={handleDownloadCsv}>
              CSVダウンロード
            </button>
          )}
        </div>

        {items.length === 0 ? (
          <div className="empty-state">
            <p>まだデータが取得されていません。上のボタンからデータを取得してください。</p>
          </div>
        ) : (
          <>
            <div className="table-responsive">
              <table className="data-table">
                <thead>
                  <tr>
                    <th>画像</th>
                    <th>タイトル / 記事名</th>
                    <th>価格 / いいね</th>
                    <th>作成日 / 詳細</th>
                    <th>リンク</th>
                  </tr>
                </thead>
                <tbody>
                  {/* ★ 現在のページに表示する20件分だけをスライスしてループする */}
                  {items
                    .slice((currentPage - 1) * itemsPerPage, currentPage * itemsPerPage)
                    .map((item, index) => (
                      <tr key={index}>
                        <td>
                          {item.image ? (
                            <img src={item.image} alt="" className="thumb-img" style={{ width: '32px', height: '32px', borderRadius: '50%' }} />
                          ) : (
                            <span className="no-image">-</span>
                          )}
                        </td>
                        <td className="product-title">
                          {item.title || '名称なし'}
                          {item.author && <div style={{ fontSize: '0.85rem', color: '#666' }}>by {item.author}</div>}
                        </td>
                        <td>
                          {item.likes !== undefined ? `❤️ ${item.likes} いいね` : (item.price || '-')}
                        </td>
                        <td>
                          {item.published_at ? item.published_at.replace('T', ' ').substring(0, 10) : '-'}
                        </td>
                        <td>
                          {item.url ? (
                            <a href={item.url} target="_blank" rel="noreferrer" className="link-btn">
                              開く
                            </a>
                          ) : (
                            '-'
                          )}
                        </td>
                      </tr>
                    ))}
                </tbody>
              </table>
            </div>

            {/* ★ ページネーションのコントロールボタン（<< と >> を追加） */}
            <div className="pagination-controls" style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', gap: '10px', marginTop: '20px', flexWrap: 'wrap' }}>

              {/* << (先頭ページへ) */}
              <button
                className="btn btn-secondary"
                onClick={() => setCurrentPage(1)}
                disabled={currentPage === 1}
              >
                &lt;&lt; 先頭
              </button>

              {/* 前へ */}
              <button
                className="btn btn-secondary"
                onClick={() => setCurrentPage(p => Math.max(p - 1, 1))}
                disabled={currentPage === 1}
              >
                &lt; 前へ
              </button>

              <span>
                全 <strong>{items.length}</strong> 件中 / <strong>{currentPage}</strong> ページ (全 {Math.ceil(items.length / itemsPerPage)} ページ)
              </span>

              {/* 次へ */}
              <button
                className="btn btn-secondary"
                onClick={() => setCurrentPage(p => Math.min(p + 1, Math.ceil(items.length / itemsPerPage)))}
                disabled={currentPage >= Math.ceil(items.length / itemsPerPage)}
              >
                次へ &gt;
              </button>

              {/* >> (最後尾ページへ) */}
              <button
                className="btn btn-secondary"
                onClick={() => setCurrentPage(Math.ceil(items.length / itemsPerPage))}
                disabled={currentPage >= Math.ceil(items.length / itemsPerPage)}
              >
                最後 &gt;&gt;
              </button>
            </div>
          </>
        )}
      </section>
    </div>
  );
}