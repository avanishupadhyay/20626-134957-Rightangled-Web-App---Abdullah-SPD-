<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Prescription</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            /* font-size: 12px; */
            color: #000;
            line-height: 1.6;
        }

        .header,
        .footer {
            text-align: center;
        }

        .header h2 {
            margin: 0;
            /* font-size: 18px; */
            text-transform: uppercase;
        }

        .section {
            margin: 20px 0;
        }

        .info-table,
        .product-table {
            width: 100%;
            border-collapse: collapse;
        }

        .info-table td {
            padding: 4px;
            vertical-align: top;
        }

        .product-table th,
        .product-table td {
            border: 1px solid #000;
            padding: 8px;
        }

        .note {
            margin-top: 20px;
            /* font-size: 11px; */
            font-style: italic;
        }

        .signature-block {
            margin-top: 40px;
            /* font-size: 12px; */
        }

        .top-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }

        .logo-container img {
            max-height: 70px;
        }

        .prescriber-details {
            /* font-size: 12px; */
            text-align: left;
        }

        /* p {
            font-size: small
        } */

        strong{
            color: #001CD7;
            font-size: 13px;
            /* font-weight: 500; */
        }
        td{
            font-size:13px;
        }
         p{
            font-size:13px;
        }
    </style>
</head>
<?php 
/* ?> ?>

<body>

    <div class="top-section">
        {{-- Left Side: Logo --}}

        <div class="logo-container">
            {{-- <img src="{{ public_path('storage/configuration-images/' . config('Site.logo')) }}" alt="Rightangled Logo"> --}}
            <img src="https://rightangled.24livehost.com/storage/configuration-images/logo-1748949654.png"
                alt="Rightangled Logo">
        </div>


        {{-- Right Side: Prescriber Info --}}
        <div class="prescriber-details">
            <p><strong>Order Number:</strong> {{ $order->name }}</p>
            <p><strong>Issue Date:</strong> {{ $order->updated_at }}</p>
            <p><strong>Note:</strong> dispensed</p>
            <p><strong>Patinets DOB:</strong> {{ $patient_s_dob }}</p>
            {{-- <p><strong>Approved:</strong> {{ $approval == 1 ? 'true' : 'false' }}</p> --}}
            <strong>Approval Status:</strong> {{ $approval === 'true' ? 'true' : 'false' }}


            <p><strong>Prescriber</strong> {{ $prescriber_s_name }}</p>
            <p><strong>Prescriber’s Reg Number:</strong> {{ $prescriber_reg }}</p>

        </div>
    </div>

    <div class="section">
        <table class="info-table">
            <tr>
                <td colspan="2">
                    <strong>Patient Address:</strong><br>
                    {{ $orderData['shipping_address']['address1'] ?? '' }}<br>
                    {{ $orderData['shipping_address']['city'] ?? '' }},
                    {{ $orderData['shipping_address']['province'] ?? '' }}
                    {{ $orderData['shipping_address']['zip'] ?? '' }}<br>
                    {{ $orderData['shipping_address']['country'] ?? '' }}<br>
                    Tel: {{ $orderData['shipping_address']['phone'] ?? '' }}<br>
                    Email: {{ $orderData['email'] ?? '' }}
                </td>
                <td colspan="2">
                    <strong>Rightangled Clinic</strong><br>
                    {{ config('Site.location') }}<br>
                    Zip: W6 0LT<br>
                    Gphc number: {{ $gphc_number }}<br>
                    Tel: {{ config('Site.contact') }}<br>
                    Email: {{ config('Site.email') }}
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <table class="product-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Direction of Use</th>
                    <th>Qty</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($items as $item)
                    <tr>
                        <td>{{ $item['title'] ?? 'N/A' }}</td>
                        <td>{{ $item['direction_of_use'] ?? 'Not available' }}</td>
                        <td>{{ $item['quantity'] ?? 0 }}</td>
                    </tr>
                @endforeach
            </tbody>

        </table>
    </div>

    {{-- <p style="display: flex;align-items: center;">
        <strong>Prescriber’s Signature:</strong> 
        <img src="{{ $prescriber_signature }}" alt="Signature" style="width: 150px; height:80px; object-fit: contain;"  />
    </p> --}}
    <p style="margin: 0; padding: 0;">
        <span style="font-weight: bold; vertical-align: middle; display: inline-block; margin-right: 10px;">
            Prescriber’s Signature:
        </span>
        <img src="{{ $prescriber_signature }}" alt="Signature"
            style="vertical-align: middle; width: 150px; height: 80px; object-fit: contain;" />
    </p>

    <p class="note">
        <strong>The Above Named Patient is Under Our Care For Ongoing Treatment</strong><br>
        We may adjust their dosage as needed for this specific medication, following clinical guidelines, to ensure
        optimal
        care.
        For any details or updates on their treatment, please feel free to contact us.
    </p>

    <div class="signature-block">
        <p>Timar Misghina, Superintendent Pharmacist at Wegoss Pharmacy. GPHC Number: 2221039</p>
        <p>Rightangled Clinic, CQC registered healthcare provider (Reg number: 1-401176984) at
            {{ config('Site.location') }}</p>
        <p>Email: {{ config('Site.email') }}| Website: www.rightangled.com</p>

    </div>

</body>
<?php */ ?>
<body>
    <table style="width: 100%">
        <tr>
            <td style="display:block;width:50%"> 
                <img src="{{ public_path('storage/configuration-images/' . config('Site.logo')) }}" alt="Rightangled Logo" style="width:200px">
                {{-- <h1><strong>RIGHTANGLED</strong></h1> --}}
            </td>
            <td style="width:50%">
                <table style="width: 100%">
                    <thead>
                        <tr>
                            <td>
                                <span style="font-size: 1em;">PRESCRIPTION</span>
                            </td>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Order Number: </strong>{{ $order->updated_at }}</td>
                        </tr>
                        <tr>
                            <td><strong>Issue Date:</strong>  {{ now()->format('Y-m-d') }}</td>
                        </tr>
                        <tr>
                            <td><strong>Note: </strong>dispensed</td>
                        </tr>
                        <tr>
                            <td><strong>Patinets DOB: </strong>{{ $patient_s_dob }}</td>
                        </tr>
                        <tr>
                            <td><strong>Approval Status: </strong>{{ $approval === 'true' ? 'true' : 'false' }}</td>
                        </tr>
                        <tr>
                            <td><strong>Prescriber: </strong>{{ $prescriber_s_name }}</td>
                        </tr>
                        <tr>
                            <td><strong>Prescriber’s Reg Number: </strong>{{ $prescriber_reg }}</td>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>
        <tr>
            <td>
                <h3><strong>PATIENT</strong></h3><br>
                {{ $orderData['shipping_address']['name'] ?? '' }}<br>
                {{ $orderData['shipping_address']['address1'] ?? '' }}<br>
                {{ $orderData['shipping_address']['city'] ?? '' }},
                {{ $orderData['shipping_address']['province'] ?? '' }}
                {{ $orderData['shipping_address']['zip'] ?? '' }}<br>
                {{ $orderData['shipping_address']['country'] ?? '' }}<br>
                Tel: {{ $orderData['shipping_address']['phone'] ?? '' }}<br>
                Email: {{ $orderData['email'] ?? '' }}
            </td>
            <td>
                <h3><strong>RIGHTANGLED CLINIC</strong></h3><br>
                {{ config('Site.location') }}<br>
                Zip: W6 0LT<br>
                Gphc number: {{ $gphc_number }}<br>
                Tel: {{ config('Site.contact') }}<br>
                Email: {{ config('Site.email') }}
            </td>
        </tr>
    </table>
    <table style="width: 100%">
        <tr style="background:#7F9BFF;padding:5px;">
            <td style="width:90%;padding:10px">
                <strong>ITEMS</strong>
            </td>
            <td style="width:10%;padding:10px;text-align:center">
                <strong>QTY</strong>
            </td>
        </tr>
        @foreach ($items as $item)
            <tr>
                <td style="width:90%;padding:10px 15px;">{{ $item['title'] ?? 'N/A' }} <br> {{ $item['direction_of_use'] ?? 'Not available' }}</td>
                <td style="width:10%;text-align:center">{{ $item['quantity'] ?? 0 }}</td>
            </tr>
        @endforeach
    </table>
    <br>
    <table style="width: 100%">
        <tr>
            <td>
                <p style="display: flex;align-items: center;gap: 15px">
                    <strong>Signature:</strong> 
                    <img src="{{ $prescriber_signature }}" alt="Signature" style="width: 200px;object-fit:contain"  />
                </p>
            </td>
        </tr>
        <tr>
            <td style="text-align: center"> 
                <p class="note">
                    <strong>The Above Named Patient is Under Our Care For Ongoing Treatment</strong><br>
                    We may adjust their dosage as needed for this specific medication, following clinical guidelines, to ensure
                    optimal
                    care.
                    For any details or updates on their treatment, please feel free to contact us.
                </p>

                <div class="signature-block">
                    <p>Timar Misghina, Superintendent Pharmacist at Wegoss Pharmacy. GPHC Number: 2221039</p>
                    <p>Rightangled Clinic, CQC registered healthcare provider (Reg number: 1-401176984) at
                        {{ config('Site.location') }}</p>
                    <p>Email: {{ config('Site.email') }}| Website: www.rightangled.com</p>

                </div>
            </td>
        </tr>
    </table>
</body>
</html>
