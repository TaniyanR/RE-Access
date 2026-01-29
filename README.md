# RE:Access（リ・アクセス）

RE:Access（リ・アクセス）は、**相互RSS／相互リンクを通じてアクセスの循環を可視化・還元する WordPress プラグイン**です。

単なるアクセス解析ではなく、  
**IN（流入）/ OUT（流出）を軸にしたサイト間の行き来**を把握し、  
健全な相互関係を継続的に運用することを目的としています。

---

## コンセプト

- アクセスを「解析」するのではなく「循環」させる
- 相互リンク・相互RSSを健全に管理・還元する
- 軽量・シンプル・PHP中心設計

RE:Access は、アクセス数の大小を競うツールではありません。  
**誰から来て、どこへ返しているのか**という流れを把握し、  
相互関係を"続ける"ための基盤として設計されています。

---

## インストール

### 要件

- WordPress 6.x 以降
- PHP 8.1 以上（8.3 推奨）

### インストール方法

1. **ダウンロード**: リリースページから最新版の `RE-Access.zip` をダウンロード
2. **アップロード**: WordPress管理画面の「プラグイン」→「新規追加」→「プラグインのアップロード」からzipファイルをアップロード
3. **有効化**: アップロード後、「プラグインを有効化」をクリック
4. **確認**: 管理画面左メニューに「RE:Access」が表示されることを確認

または、手動インストール：
```bash
cd /path/to/wordpress/wp-content/plugins/
unzip RE-Access.zip
```

WordPress管理画面からプラグインを有効化してください。

---

## 開発者向け情報

### プラグイン構造

```
re-access/
├── re-access.php      # メインプラグインファイル
├── composer.json      # Composer設定
├── composer.lock      # 依存関係ロック
└── vendor/           # Composer依存パッケージ（配布に含む）
```

### バージョン管理

プラグインのバージョンは `re-access.php` のヘッダーと `RE_ACCESS_VERSION` 定数で管理されています。
有効化時に WordPress options に `re_access_version` キーで保存されます。

### 自動更新

GitHub Releases を利用した自動更新機能を搭載しています。
- リポジトリ: https://github.com/TaniyanR/RE-Access
- 更新チェッカー: yahnis-elsts/plugin-update-checker

---

## 主な機能（予定）

### アクセス計測（サイト全体）

- IN（流入）
- OUT（流出）
- PV（ページビュー）
- UU（ユニークユーザー）

※ 特定リンク経由に限らず、サイト全体のアクセスを計測します。

---

### ダッシュボード

- 上部KPI表示
  - 総アクセス数
  - UU
  - PV
  - 総OUT
- アクセス推移グラフ
- 日別詳細テーブル
- 期間指定（1日 / 1週間 / 1ヶ月）
- 前日 / 前週 / 前月 比較

※ ダッシュボードUIは固定設計です。

---

### 逆アクセスランキング

- 設定画面とフロント表示を1ページに統合
- 表形式表示（rank / web / IN / OUT）
- 期間指定（1日 / 1週間 / 1ヶ月）
- 表示件数指定
- IN / OUT 表示切替
- カラー・幅カスタマイズ対応
- ショートコード対応

```text
[reaccess_ranking period="" limit="" show_in="" show_out="" width="" accent="" head_bg="" text=""]
```

### リンク設定（スロット方式）

スロット 1〜10

各スロットに以下を設定可能：
- 説明
- HTMLテンプレート
- CSSテンプレート
- 表示プレビュー
- ショートコード

使用可能変数：
- [rr_site_name]
- [rr_site_url]
- [rr_site_desc]

### RSS設定（スロット方式）

スロット 1〜10

表示件数指定可能

HTML / CSS テンプレ編集対応

使用可能変数：
- [rr_item_image]
- [rr_site_name]
- [rr_item_title]
- [rr_item_url]
- [rr_item_date]

※ RSS記事に画像が含まれない場合、代替画像は使用せず テキストリンクのみ表示されます。

### お知らせ機能

サイトの新規登録・承認・削除時に自動ログ生成

DBには最新N件のみ保存（軽量）

ショートコードで表示可能：
- [reaccess_notice]
- [reaccess_notice_latest]

---

## 設計方針

- 外部APIなし
- 重いCron処理なし
- PHP中心の軽量設計
- 必要最小限のデータ保存
- 出力HTMLはすべてフィルターフック対応

---

## ライセンス

GPL v2 or later
