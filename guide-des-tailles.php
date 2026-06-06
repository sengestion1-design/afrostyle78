<?php
require_once 'config/config.php';
require_once 'config/database.php';

$pageTitle = 'Guide des tailles';
require_once 'includes/header.php';
?>

<style>
.size-guide-hero {
    background: linear-gradient(135deg, var(--dark) 0%, #1a1208 100%);
    padding: 80px 0 60px;
    text-align: center;
    border-bottom: 1px solid var(--gold-dim, rgba(200,146,26,0.3));
}
.size-guide-hero .section-eyebrow {
    letter-spacing: 0.2em;
    font-size: 0.72rem;
    text-transform: uppercase;
    color: var(--gold);
    margin-bottom: 12px;
}
.size-guide-hero h1 {
    font-family: 'Cormorant Garamond', serif;
    font-size: clamp(2.2rem, 5vw, 3.5rem);
    font-weight: 600;
    color: var(--cream, #f5f0e8);
    margin: 0 0 16px;
    line-height: 1.15;
}
.size-guide-hero p {
    color: var(--text-muted, #a09070);
    font-size: 0.92rem;
    max-width: 520px;
    margin: 0 auto;
    line-height: 1.8;
}

.size-guide-section {
    padding: 70px 0 30px;
}
.size-guide-section + .size-guide-section {
    padding-top: 20px;
}

.sg-category-label {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    font-size: 0.68rem;
    letter-spacing: 0.22em;
    text-transform: uppercase;
    color: var(--gold);
    margin-bottom: 12px;
}
.sg-category-label::before,
.sg-category-label::after {
    content: '';
    display: block;
    width: 30px;
    height: 1px;
    background: var(--gold);
    opacity: 0.5;
}

.sg-title {
    font-family: 'Cormorant Garamond', serif;
    font-size: clamp(1.6rem, 3vw, 2.2rem);
    font-weight: 600;
    color: var(--cream, #f5f0e8);
    margin: 0 0 32px;
}

.sg-table-wrap {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    border-radius: 4px;
    border: 1px solid rgba(200,146,26,0.2);
}

.sg-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
    min-width: 480px;
}

.sg-table thead tr {
    background: rgba(200,146,26,0.12);
}
.sg-table thead th {
    padding: 14px 20px;
    text-align: center;
    font-family: 'Syne', sans-serif;
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--gold);
    border-bottom: 1px solid rgba(200,146,26,0.25);
    white-space: nowrap;
}
.sg-table thead th:first-child {
    text-align: left;
}

.sg-table tbody tr {
    border-bottom: 1px solid rgba(255,255,255,0.05);
    transition: background 0.15s;
}
.sg-table tbody tr:last-child {
    border-bottom: none;
}
.sg-table tbody tr:hover {
    background: rgba(200,146,26,0.06);
}

.sg-table tbody td {
    padding: 13px 20px;
    text-align: center;
    color: var(--text-muted, #a09070);
    vertical-align: middle;
}
.sg-table tbody td:first-child {
    text-align: left;
    font-family: 'Syne', sans-serif;
    font-weight: 700;
    font-size: 0.82rem;
    letter-spacing: 0.08em;
    color: var(--cream, #f5f0e8);
}

.sg-note {
    margin-top: 60px;
    padding: 30px 36px;
    background: rgba(200,146,26,0.07);
    border: 1px solid rgba(200,146,26,0.25);
    border-left: 3px solid var(--gold);
    border-radius: 2px;
}
.sg-note-title {
    font-family: 'Syne', sans-serif;
    font-weight: 700;
    font-size: 0.78rem;
    letter-spacing: 0.15em;
    text-transform: uppercase;
    color: var(--gold);
    margin-bottom: 10px;
}
.sg-note p {
    color: var(--text-muted, #a09070);
    font-size: 0.88rem;
    line-height: 1.85;
    margin: 0 0 8px;
}
.sg-note p:last-child { margin: 0; }
.sg-note a {
    color: var(--gold);
    text-decoration: none;
    border-bottom: 1px solid rgba(200,146,26,0.4);
    transition: border-color 0.2s;
}
.sg-note a:hover { border-color: var(--gold); }

.sg-divider {
    border: none;
    border-top: 1px solid rgba(255,255,255,0.07);
    margin: 50px 0;
}

.sg-how-to {
    padding: 50px 0 70px;
}
.sg-how-to-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 24px;
    margin-top: 32px;
}
.sg-how-card {
    padding: 24px 20px;
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 2px;
    text-align: center;
}
.sg-how-card .icon {
    font-size: 1.8rem;
    margin-bottom: 12px;
    display: block;
}
.sg-how-card strong {
    display: block;
    font-family: 'Syne', sans-serif;
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--cream, #f5f0e8);
    margin-bottom: 8px;
}
.sg-how-card span {
    font-size: 0.8rem;
    color: var(--text-muted, #a09070);
    line-height: 1.7;
}
</style>

<!-- HERO -->
<div class="size-guide-hero">
    <div class="container">
        <div class="section-eyebrow">AfroStyle78</div>
        <h1>Guide des tailles</h1>
        <p>Trouvez votre taille idéale parmi nos confections. Toutes les mesures sont en centimètres, prises à plat à même le corps.</p>
    </div>
</div>

<!-- WOMEN TABLE -->
<section class="size-guide-section">
    <div class="container">
        <div class="sg-category-label">Femme</div>
        <h2 class="sg-title">Tableau des tailles — Femme</h2>
        <div class="sg-table-wrap">
            <table class="sg-table">
                <thead>
                    <tr>
                        <th>Taille</th>
                        <th>Tour poitrine (cm)</th>
                        <th>Tour taille (cm)</th>
                        <th>Tour hanches (cm)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>XS</td>
                        <td>80 – 84</td>
                        <td>60 – 64</td>
                        <td>85 – 89</td>
                    </tr>
                    <tr>
                        <td>S</td>
                        <td>85 – 89</td>
                        <td>65 – 69</td>
                        <td>90 – 94</td>
                    </tr>
                    <tr>
                        <td>M</td>
                        <td>90 – 95</td>
                        <td>70 – 75</td>
                        <td>95 – 100</td>
                    </tr>
                    <tr>
                        <td>L</td>
                        <td>96 – 101</td>
                        <td>76 – 82</td>
                        <td>101 – 106</td>
                    </tr>
                    <tr>
                        <td>XL</td>
                        <td>102 – 108</td>
                        <td>83 – 90</td>
                        <td>107 – 113</td>
                    </tr>
                    <tr>
                        <td>XXL</td>
                        <td>109 – 116</td>
                        <td>91 – 99</td>
                        <td>114 – 121</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<hr class="sg-divider" style="max-width:1200px; margin:0 auto;">

<!-- MEN TABLE -->
<section class="size-guide-section">
    <div class="container">
        <div class="sg-category-label">Homme</div>
        <h2 class="sg-title">Tableau des tailles — Homme</h2>
        <div class="sg-table-wrap">
            <table class="sg-table">
                <thead>
                    <tr>
                        <th>Taille</th>
                        <th>Tour poitrine (cm)</th>
                        <th>Tour taille (cm)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>S</td>
                        <td>88 – 93</td>
                        <td>72 – 77</td>
                    </tr>
                    <tr>
                        <td>M</td>
                        <td>94 – 99</td>
                        <td>78 – 84</td>
                    </tr>
                    <tr>
                        <td>L</td>
                        <td>100 – 106</td>
                        <td>85 – 92</td>
                    </tr>
                    <tr>
                        <td>XL</td>
                        <td>107 – 114</td>
                        <td>93 – 101</td>
                    </tr>
                    <tr>
                        <td>XXL</td>
                        <td>115 – 122</td>
                        <td>102 – 110</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- HOW TO MEASURE -->
<section class="sg-how-to">
    <div class="container">
        <div class="sg-category-label">Conseils</div>
        <h2 class="sg-title">Comment prendre ses mesures</h2>
        <div class="sg-how-to-grid">
            <div class="sg-how-card">
                <span class="icon">📏</span>
                <strong>Tour de poitrine</strong>
                <span>Mesurez à l'endroit le plus fort de la poitrine, ruban bien horizontal dans le dos.</span>
            </div>
            <div class="sg-how-card">
                <span class="icon">✂️</span>
                <strong>Tour de taille</strong>
                <span>Placez le ruban autour de la partie la plus étroite du buste, entre côtes et hanches.</span>
            </div>
            <div class="sg-how-card">
                <span class="icon">👗</span>
                <strong>Tour de hanches</strong>
                <span>Mesurez à l'endroit le plus fort des hanches, environ 20 cm sous la taille.</span>
            </div>
            <div class="sg-how-card">
                <span class="icon">📌</span>
                <strong>Conseil général</strong>
                <span>Prenez vos mesures en sous-vêtements, ruban non serré — juste à plat contre le corps.</span>
            </div>
        </div>

        <!-- SUR-MESURE NOTE -->
        <div class="sg-note">
            <div class="sg-note-title">Option sur-mesure disponible</div>
            <p>Aucune taille standard ne correspond à votre morphologie ? Pas de problème — toutes nos créations peuvent être confectionnées sur-mesure à partir de vos propres mensurations.</p>
            <p>Sur la page produit, activez l'option <strong style="color:var(--cream);">Commander sur-mesure</strong> et renseignez vos mesures directement dans le formulaire. Délai de confection : 7 à 14 jours ouvrables. Pour toute question, <a href="<?= SITE_URL ?>/sur-mesure">contactez-nous ici</a>.</p>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
