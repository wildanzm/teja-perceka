<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alokasi Laba - {{ $entityName }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 11px;
            color: #171717;
            background: #fff;
            margin: 30px;
            line-height: 1.4;
        }

        /* HEADER */
        .doc-header {
            text-align: center;
            margin-bottom: 20px;
        }
        .doc-header .entity  { font-size: 13px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; }
        .doc-header .title   { font-size: 12px; font-weight: bold; text-transform: uppercase; margin-top: 3px; }
        .doc-header .period  { font-size: 11px; font-weight: bold; text-transform: uppercase; color: #000; margin-top: 2px; }

        /* TABLE */
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #404040;
            text-align: left;
            padding: 6px 8px;
            border-bottom: 2px solid #171717;
        }
        .data-table th.c { text-align: center; }
        .data-table th.r { text-align: right; }

        .data-table td {
            padding: 5px 8px;
            border-bottom: 1px solid #e5e5e5;
            vertical-align: middle;
        }

        .c { text-align: center; }
        .r { text-align: right; }

        /* NET INCOME - bold opening row */
        .row-laba td {
            font-weight: bold;
            font-size: 12px;
            background-color: #f5f5f5;
            border-bottom: 2px solid #171717;
        }

        /* Allocation item rows (indented) */
        .row-item td:first-child {
            padding-left: 20px;
        }

        /* Profit after deductions - section closing */
        .row-setelah td {
            font-weight: bold;
            border-top: 1px solid #a3a3a3;
            border-bottom: 2px solid #171717;
            background-color: #f9f9f9;
        }

        /* AD/ART section title */
        .row-section td {
            font-weight: bold;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
            color: #404040;
            padding-top: 12px;
            border-bottom: 1px solid #e5e5e5;
        }

        /* AD/ART total */
        .row-total td {
            font-weight: bold;
            border-bottom: none;
            border-top: 2px solid #171717;
            background-color: #f9f9f9;
        }

        .empty {
            font-style: italic;
            color: #737373;
        }

        /* SUMMARY BOX */
        .summary-box {
            width: 100%;
            margin-top: 18px;
            border-collapse: collapse;
        }
        .summary-box td {
            padding: 4px 8px;
            border: 1px solid #e5e5e5;
        }
        .summary-box .lbl {
            font-weight: bold;
            background: #fafafa;
            width: 50%;
        }

        /* FOOTER */
        .footer-sig {
            width: 100%;
            margin-top: 36px;
        }
        .sig-box {
            float: right;
            text-align: center;
            width: 220px;
        }
        .sig-name {
            font-weight: bold;
            text-transform: uppercase;
            border-bottom: 1px solid #000;
            display: inline-block;
            margin-bottom: 2px;
        }
    </style>
</head>
<body>

    <div class="doc-header">
        <div class="entity">{{ $entityName }}</div>
        <div class="title">Laporan Alokasi Laba</div>
        <div class="period">{{ $periodLabel }}</div>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Keterangan</th>
                <th class="c" style="width: 18%;">Jenis Alokasi</th>
                <th class="c" style="width: 18%;">Metode Perhitungan</th>
                <th class="r" style="width: 24%;">Jumlah (Rp)</th>
            </tr>
        </thead>
        <tbody>
            {{-- Net income --}}
            <tr class="row-laba">
                <td colspan="3">LABA BERSIH</td>
                <td class="r">Rp {{ number_format($netIncome, 0, ',', '.') }}</td>
            </tr>

            {{-- Deduction items --}}
            @forelse($deductionRows as $row)
                <tr class="row-item">
                    <td>
                        {{ $row['description'] }}
                        @if(!empty($row['account']))
                            <div style="font-size: 9px; color: #666;">{{ $row['account']->code }} - {{ $row['account']->name }}</div>
                        @endif
                    </td>
                    <td class="c">
                        {{ $row['flow_type'] === 'outflow' ? 'Penyaluran Laba' : 'Cadangan Modal' }}
                    </td>
                    <td class="c">
                        {{ $row['calc_type'] === 'nominal' ? 'Nominal Tetap' : number_format($row['percentage'], 2, '.', '') . '%' }}
                    </td>
                    <td class="r">Rp {{ number_format($row['amount'], 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="empty">— Belum ada pos Pengurang —</td>
                </tr>
            @endforelse

            {{-- Profit after deductions --}}
            <tr class="row-setelah">
                <td colspan="3">Laba/Rugi Bersih setelah {{ $deductionLabel }}</td>
                <td class="r">Rp {{ number_format($netIncomeAfterDeductions, 0, ',', '.') }}</td>
            </tr>

            {{-- AD/ART section --}}
            <tr class="row-section">
                <td colspan="4">Alokasi Laba Bersih sesuai AD/ART</td>
            </tr>

            @forelse($adArtRows as $row)
                <tr class="row-item">
                    <td>
                        {{ $row['description'] }}
                        @if(!empty($row['account']))
                            <div style="font-size: 9px; color: #666;">{{ $row['account']->code }} - {{ $row['account']->name }}</div>
                        @endif
                    </td>
                    <td class="c">
                        {{ $row['flow_type'] === 'outflow' ? 'Penyaluran Laba' : 'Cadangan Modal' }}
                    </td>
                    <td class="c">
                        {{ $row['calc_type'] === 'nominal' ? 'Nominal Tetap' : number_format($row['percentage'], 2, '.', '') . '%' }}
                    </td>
                    <td class="r">Rp {{ number_format($row['amount'], 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="empty">— Belum ada pos AD/ART —</td>
                </tr>
            @endforelse

            {{-- AD/ART total --}}
            @if(count($adArtRows) > 0)
                <tr class="row-total">
                    <td colspan="2">TOTAL AD/ART</td>
                    <td class="c">{{ number_format($totalAdArtPercent, 2, '.', '') }}%</td>
                    <td class="r">Rp {{ number_format($totalAdArtAmount, 0, ',', '.') }}</td>
                </tr>
            @endif

            @if(count($deductionRows) === 0 && count($adArtRows) === 0)
                <tr>
                    <td colspan="4" class="empty" style="text-align: center; padding: 14px;">
                        Belum ada pos alokasi untuk periode ini.
                    </td>
                </tr>
            @endif
        </tbody>
    </table>

    {{-- Summary (mirrors the summary cards on the profit allocation page) --}}
    <table class="summary-box">
        <tr>
            <td class="lbl">Total Laba Bersih</td>
            <td class="r" style="font-weight: bold;">Rp {{ number_format($netIncome, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="lbl">Penyaluran Laba (Kas Keluar)</td>
            <td class="r" style="font-weight: bold;">Rp {{ number_format($totalOutflow ?? 0, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="lbl">Cadangan Modal (Laba Ditahan)</td>
            <td class="r" style="font-weight: bold;">Rp {{ number_format($totalRetained ?? 0, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="lbl">Sisa Laba ({{ ($unallocatedProfit ?? 0) < 0 ? 'Melebihi Laba' : 'Belum Dibagi' }})</td>
            <td class="r" style="font-weight: bold; {{ ($unallocatedProfit ?? 0) < 0 ? 'color: #b91c1c;' : '' }}">Rp {{ number_format($unallocatedProfit ?? 0, 0, ',', '.') }}</td>
        </tr>
    </table>

    @if($showSignature ?? true)
    <div class="footer-sig">
        <div class="sig-box">
            <p style="margin-bottom: 50px;">TEJA, {{ $signatureDate }}</p>
            <div>
                <span class="sig-name">{{ $signatory }}</span>
            </div>
            <p>{{ $position }}</p>
        </div>
        <div style="clear: both;"></div>
    </div>
    @endif

</body>
</html>
