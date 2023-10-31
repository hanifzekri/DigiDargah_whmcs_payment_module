# DigiDargah Crypto Payment Gateway for WHMCS

## Table of Contents

- [Description](#description)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [Contributing](#contributing)
- [Troubleshooting](#troubleshooting)
- [License](#license)
- [Contact Information](#contact-information)
- [Changelog](#changelog)

---

## Description

The DigiDargah Crypto Payment Gateway for WHMCS is a plugin that allows WHMCS users to accept cryptocurrency payments via the DigiDargah platform. This plugin is developed by Hanif Zekri Astaneh and is provided by DigiDargah.com.

- **Plugin Name:** DigiDargah Crypto Payment Gateway for WHMCS
- **Version:** 1.1
- **Author:** DigiDargah.com
- **Author URI:** [https://digidargah.com](https://digidargah.com)
- **Author Email:** info@digidargah.com
- **Text Domain:** DigiDargah_whmcs_payment_module
- **WC tested up to:** 8.8
- **Copyright (C):** 2020 DigiDargah
- **License:** [GPLv3 or later](http://www.gnu.org/licenses/gpl-3.0.html)

---

## Installation

After submitting your website on DigiDargah.com and getting an API key, to install the DigiDargah Crypto Payment Gateway for WHMCS, follow these steps:

1. Copy the `digidargah.php` file to the `modules/gateways/` directory of your WHMCS installation.

2. Log in to your WHMCS admin panel.

3. Go to the "Setup" menu and select "Payment Gateways."

4. Under the "All Payment Gateways" tab, you should see "DigiDargah" in the list of gateways. Click on it to configure the settings.

5. Configure the gateway settings as described in the [Configuration](#configuration) section below.

6. Once configured, save your settings.

---

## Configuration

To configure the DigiDargah Crypto Payment Gateway for WHMCS, follow these steps:

1. Log in to your WHMCS admin panel.

2. Go to the "Setup" menu and select "Payment Gateways."

3. Under the "All Payment Gateways" tab, select "DigiDargah" from the list of gateways.

4. Configure the following settings:

   - **API Key:** Enter your DigiDargah API key. You can obtain this key by visiting [https://digidargah.com/cryptosite](https://digidargah.com/cryptosite).

   - **Pay Currency:** Specify the currencies available for payment. You can list multiple currencies separated by a dash (e.g., BTC-ETH-LTC).

   - **Success Message:** Customize the message to display to customers after a successful payment. You can use placeholders like `{invoice_id}` and `{request_id}` to display the invoice and request IDs.

   - **Failed Message:** Customize the message to display to customers after a failed payment. You can use placeholders like `{invoice_id}` and `{request_id}` to display the invoice and request IDs.

5. Save your settings.

---

## Usage

Once the DigiDargah Crypto Payment Gateway is configured, customers can use it to make payments for their invoices. Here's how it works:

1. Customers can view and select the DigiDargah payment option when paying their invoices in the WHMCS client area.

2. Upon selecting DigiDargah as the payment method, customers will be redirected to complete the payment on the DigiDargah platform.

3. After making the payment on DigiDargah, customers will be redirected back to WHMCS.

4. WHMCS will update the invoice status based on the payment result. If the payment is successful, the invoice status will change to "Paid."

---

## Contributing

If you want to contribute to this project or report issues, please visit the GitHub repository: [DigiDargah Crypto Payment Gateway for WHMCS](https://github.com/hanifzekri/DigiDargah_whmcs_payment_module).

---

## Troubleshooting

If you encounter any issues or have questions about the DigiDargah Crypto Payment Gateway for WooCommerce, please refer to the [official documentation](https://digidargah.com) or contact our support team at [info@digidargah.com](mailto:info@digidargah.com).

---

## License

This project is licensed under the GPLv3 or later. See the [GNU General Public License](http://www.gnu.org/licenses/gpl-3.0.html) for more details.

---

## Contact Information

If you have any questions or need assistance, please contact us at [info@digidargah.com](mailto:info@digidargah.com).

---

## Changelog

- 1.1, feb 18, 2023
Bugs fixed

- 1.0, jan 15, 2023
First release.

---

Thank you for using the DigiDargah Crypto Payment Gateway for WooCommerce! We appreciate your business.

---

*Copyright (C) 2020 DigiDargah*
