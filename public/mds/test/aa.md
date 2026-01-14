# 学位論文のお作法

学位論文とは、**博士論文**、**修士論文**（修士2年生が執筆）、**学士論文**（学部4年生が執筆）の3種類を指します。
本ページは主に**修士論文**と**学士論文**を対象とした入門ページです。なお、論文の書き方全般については、以下を参照してください。

* [論文執筆のお作法](https://lecture.tetsuakibaba.jp/view.html?file=Research+Handbook%2F%E8%AB%96%E6%96%87%E5%9F%B7%E7%AD%86%E3%81%AE%E3%81%8A%E4%BD%9C%E6%B3%95.md)

> [!WARNING]
> 学位論文を書く際、先輩の学位論文を参考にして書き始める人が多いようですが、これはおすすめしません。
> 先輩の学位論文の中には参考になるものも多くありますが、締切直前に急いで書かれたもなど、品質がまちまちです。
> 参考にすべき論文は、**学会等でオーソライズされた論文**にしてください。
> つまり、先輩の研究を参考にしたい場合は、**先輩が学会等で発表した論文**を参照してください。
> 学位論文は、あくまで**フォーマットの参考として読む程度**に留めてください。

## 論文フォーマット

論文を書くにあたって、インダストリアルアートでは以下の2種類のフォーマットが利用できます。

* **TeX**
* **Word**

可能であれば **TeX の利用を推奨**しますが、Word でなければ難しい場合は Word でも構いません。

### フォーマット配布先
以下はインダストリアルアートにおける論文フォーマットです。

* **TeX フォーマット**: [https://www.overleaf.com/latex/templates/ialun-wen-tenpureto/byzxrchdyvmx](https://www.overleaf.com/latex/templates/ialun-wen-tenpureto/byzxrchdyvmx)

* **Word フォーマット**: [https://www.dropbox.com/sh/palgjpu5ttbrmh7/AAB8dEzQ9UEw5U0r6_UqZfgJa?dl=0](https://www.dropbox.com/sh/palgjpu5ttbrmh7/AAB8dEzQ9UEw5U0r6_UqZfgJa?dl=0)

---

## TeX を利用する

TeX は Word のような WYSIWYG（What You See What You Get）ではなく、HTML や Markdown に近い**記述型**の書き方をします。TeX はローカル環境構築がやや大変ですが、現在はクラウドサービスを利用することで、環境構築なしに論文執筆に集中できます。以下では、代表的な2つのサービスを紹介します。**おすすめは Overleaf です。**

## Overleaf

すでに Overleaf のアカウントを持っている場合は、以下の IA 論文テンプレートから、すぐに書き始めることができます。

* [https://www.overleaf.com/latex/templates/ialun-wen-tenpureto/byzxrchdyvmx](https://www.overleaf.com/latex/templates/ialun-wen-tenpureto/byzxrchdyvmx)

### 手動でテンプレートを導入する場合

1. [Overleaf](https://ja.overleaf.com) でアカウントを作成し、ログインする。 
2. 「新規プロジェクト」→「プロジェクトをアップロード」を選択
3. 次のページからダウンロードした zip ファイルをアップロード: [https://ja.overleaf.com/read/rhhzjkbbqznv](https://ja.overleaf.com/read/rhhzjkbbqznv)

## Cloud LaTeX（cloudlatex）

1. Cloud LaTeX でアカウントを作成してログイン：  [https://cloudlatex.io](https://cloudlatex.io)
2. 新規プロジェクトを作成
3. `main.tex` を IA フォーマットの `main.tex` と入れ替える
4. `iapaper.sty` を `main.tex` と同じ階層に追加
5. `images/sample.jpg` を追加
6. 不要なファイル, `figure` フォルダ,`figure/Sample.png`を削除

以上の設定後、**Compile（コンパイル）ボタン**を押し、PDF が正しく生成されるか確認してください。


## ローカル環境で TeX を使いたい場合
クラウド環境を使いたくない場合は、macOS では **MacTeX** の利用がおすすめです。
* [http://www.tug.org/mactex/index.html](http://www.tug.org/mactex/index.html)
また、Overleafなどのクラウド環境ではファイル容量に制限があり、博士論文などの大きなプロジェクトになると、無料プランでは論文執筆を賄えないケースが頻発します。そのような場合は、MacTexを利用したローカル環境でTeXのコンパイルができるようにしましょう。

### ローカル環境コンパイル（macOS）
無料プランの場合は保存できるファイル容量に制限があるため，修士論文や博士論文を完成させることができないことがあります．また，ネットワーク環境のない場所で執筆作業をしたい場合もあると思います．そのような場合は以下の動画の手順に従ってローカル環境でTeXの編集作業，PDF出力ができるようになります．



* チュートリアル動画（5分）https://www.loom.com/share/f277ba3c2eea4db09a34efe0b651d245?sid=3c95e7e8-426d-4927-88d6-3d53e9053c98
* mactexパッケージの国内ミラーサーバー： https://www.ctan.org/mirrors/mirmon#jp