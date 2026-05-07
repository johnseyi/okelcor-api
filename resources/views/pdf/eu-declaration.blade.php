<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Entry Certificate — {{ $declaration->order_ref }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            color: #171a20;
            background: #ffffff;
        }

        .page {
            padding: 36px 44px;
        }

        /* Header */
        .header-bar {
            border-bottom: 3px solid #f4511e;
            padding-bottom: 14px;
            margin-bottom: 18px;
        }
        .header-title {
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #171a20;
        }
        .header-subtitle {
            font-size: 11px;
            color: #5c5e62;
            margin-top: 3px;
        }
        .header-ref {
            font-size: 11px;
            color: #5c5e62;
            margin-top: 2px;
        }

        /* Section layout */
        .two-col {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        .two-col td {
            vertical-align: top;
            width: 50%;
            padding-right: 20px;
        }
        .two-col td:last-child { padding-right: 0; }

        .section {
            margin-bottom: 16px;
        }

        .section-title {
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            color: #9e9e9e;
            margin-bottom: 5px;
            border-bottom: 1px solid #eeeeee;
            padding-bottom: 3px;
        }

        .field-row {
            margin-bottom: 4px;
            line-height: 1.5;
        }
        .field-label {
            color: #5c5e62;
            display: inline;
        }
        .field-value {
            color: #171a20;
            font-weight: 700;
            display: inline;
        }

        /* Goods table */
        .goods-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
            margin-bottom: 16px;
        }
        .goods-table th {
            background-color: #f5f5f5;
            padding: 6px 10px;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            color: #5c5e62;
            text-align: left;
            border-bottom: 1px solid #dddddd;
        }
        .goods-table td {
            padding: 7px 10px;
            font-size: 11px;
            color: #171a20;
            border-bottom: 1px solid #eeeeee;
            vertical-align: top;
        }
        .goods-table .right { text-align: right; }

        /* Receipt box */
        .receipt-box {
            border: 1px solid #dddddd;
            background-color: #fafafa;
            padding: 12px 14px;
            margin-bottom: 16px;
        }

        /* Transport note */
        .transport-box {
            border: 1px solid #e0e0e0;
            background-color: #fffde7;
            padding: 10px 14px;
            margin-bottom: 16px;
        }

        /* Signature block */
        .sig-block {
            border-top: 2px solid #dddddd;
            padding-top: 14px;
            margin-top: 8px;
        }
        .sig-table {
            width: 100%;
            border-collapse: collapse;
        }
        .sig-table td {
            vertical-align: top;
            width: 50%;
            padding-right: 16px;
        }
        .sig-table td:last-child { padding-right: 0; }

        .signed-name {
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #171a20;
            margin-bottom: 6px;
        }
        .sig-image {
            max-height: 65px;
            max-width: 200px;
        }
        .sig-line {
            border-top: 1px solid #9e9e9e;
            margin-top: 10px;
            padding-top: 3px;
            font-size: 9px;
            color: #9e9e9e;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        /* Legal note */
        .legal-note {
            margin-top: 18px;
            border-top: 1px solid #eeeeee;
            padding-top: 10px;
            font-size: 9px;
            color: #9e9e9e;
            line-height: 1.6;
        }
    </style>
</head>
<body>
<div class="page">

    <!-- Header -->
    <div class="header-bar">
        <div class="header-title">Entry Certificate &mdash; Gelangensbestätigung</div>
        <div class="header-subtitle">pursuant to § 17a para. 1 UStDV &mdash; Proof of intra-community transport</div>
        <div class="header-ref">Order reference: <strong>{{ $declaration->order_ref }}</strong> &nbsp;|&nbsp; Issued: {{ $declaration->issue_date?->format('d M Y') }}</div>
    </div>

    <!-- Supplier / Recipient -->
    <table class="two-col">
        <tr>
            <td>
                <div class="section-title">1. Supplier / Consignor</div>
                <div class="field-row">
                    <strong>Okelcor</strong>
                </div>
                <div class="field-row" style="color:#5c5e62;">support@okelcor.com</div>
                <div class="field-row" style="color:#5c5e62;">okelcor.com</div>
            </td>
            <td>
                <div class="section-title">2. Recipient / Consignee</div>
                <div class="field-row"><strong>{{ $declaration->company_name }}</strong></div>
                @if ($declaration->customer_address)
                <div class="field-row" style="color:#5c5e62;">{{ $declaration->customer_address }}</div>
                @endif
                @if ($declaration->vat_number)
                <div class="field-row">
                    <span class="field-label">VAT No.: </span>
                    <span class="field-value">{{ $declaration->vat_number }}</span>
                </div>
                @endif
                <div class="field-row">
                    <span class="field-label">Country: </span>
                    <span class="field-value">{{ $declaration->country }}</span>
                </div>
            </td>
        </tr>
    </table>

    <!-- Goods -->
    <div class="section-title">3. Description of Goods</div>
    <table class="goods-table">
        <thead>
            <tr>
                <th>Commercial goods description</th>
                <th class="right" style="width:200px;">Quantity</th>
            </tr>
        </thead>
        <tbody>
            @foreach (explode("\n", $declaration->goods_description) as $line)
            @if (trim($line))
            <tr>
                <td>{{ trim($line) }}</td>
                @if ($loop->first)
                <td class="right" rowspan="{{ count(array_filter(explode('\n', $declaration->goods_description), 'trim')) }}">
                    {{ $declaration->quantity_description }}
                </td>
                @endif
            </tr>
            @endif
            @endforeach
        </tbody>
    </table>

    <!-- Receipt confirmation -->
    <div class="receipt-box">
        <div class="section-title" style="margin-bottom:8px;">4. Receipt Confirmation</div>
        <p style="margin-bottom:8px;line-height:1.6;">
            The undersigned recipient confirms that the goods listed above have been dispatched or transported
            to the territory of another EU member state.
        </p>
        <table style="width:100%;border-collapse:collapse;">
            <tr>
                <td style="width:50%;padding-right:16px;vertical-align:top;">
                    <div class="field-row">
                        <span class="field-label">EU Member State of Entry: </span>
                        <span class="field-value">{{ $declaration->member_state_of_entry }}</span>
                    </div>
                    <div class="field-row">
                        <span class="field-label">Place of Entry: </span>
                        <span class="field-value">{{ $declaration->place_of_entry }}</span>
                    </div>
                </td>
                <td style="width:50%;vertical-align:top;">
                    <div class="field-row">
                        <span class="field-label">Month / Year Received: </span>
                        <span class="field-value">{{ $declaration->month_year_received }}</span>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Transport (only if self-transported) -->
    @if ($declaration->self_transported)
    <div class="transport-box">
        <div class="section-title" style="margin-bottom:6px;">5. Means of Transport</div>
        <div class="field-row">The goods were transported by the recipient (self-transported).</div>
        @if ($declaration->month_year_transport_ended)
        <div class="field-row" style="margin-top:4px;">
            <span class="field-label">Month / Year Transport Ended: </span>
            <span class="field-value">{{ $declaration->month_year_transport_ended }}</span>
        </div>
        @endif
    </div>
    @endif

    <!-- Signature block -->
    <div class="sig-block">
        <div class="section-title" style="margin-bottom:10px;">{{ $declaration->self_transported ? '6' : '5' }}. Declaration &amp; Signature</div>
        <table class="sig-table">
            <tr>
                <td>
                    <div class="field-row" style="margin-bottom:6px;">
                        <span class="field-label">Authorised representative: </span>
                        <span class="field-value">{{ $declaration->representative_name }}</span>
                        @if ($declaration->representative_title)
                        <span style="color:#5c5e62;"> &mdash; {{ $declaration->representative_title }}</span>
                        @endif
                    </div>

                    <div class="field-row" style="margin-bottom:8px;">
                        <span class="field-label">Date of issue: </span>
                        <span class="field-value">{{ $declaration->issue_date?->format('d M Y') }}</span>
                    </div>

                    <div class="signed-name">{{ $declaration->signed_name }}</div>
                    <div class="sig-line">Signature (printed name)</div>
                </td>
                <td>
                    @if (!empty($signatureBase64))
                    <div style="margin-bottom:4px;">
                        <img src="data:image/png;base64,{{ $signatureBase64 }}" class="sig-image" alt="Signature">
                    </div>
                    <div class="sig-line">Handwritten signature</div>
                    @else
                    <div style="height:65px;border-bottom:1px solid #9e9e9e;"></div>
                    <div class="sig-line">Handwritten signature</div>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <!-- Legal note -->
    <div class="legal-note">
        <p>
            This entry certificate (Gelangensbestätigung) constitutes proof of intra-community transport pursuant to
            § 17a para. 1 Umsatzsteuer-Durchführungsverordnung (UStDV) and § 6a para. 3 Umsatzsteuergesetz (UStG).
            It confirms that the goods have been dispatched or transported to another EU member state within the meaning
            of the intra-community supply provisions.
        </p>
        <p style="margin-top:4px;">
            Generated by Okelcor &mdash; support@okelcor.com &mdash; okelcor.com
        </p>
    </div>

</div>
</body>
</html>
