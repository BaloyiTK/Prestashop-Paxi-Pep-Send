# PrestaShop PAXI / PEP Store Delivery

A PrestaShop 8+ shipping module by **Kaziara Solutions** that keeps checkout compact by exposing a single **PAXI / PEP Courier** delivery method.

## ⬇️ Download

### [Download latest version (ZIP)](https://github.com/BaloyiTK/Prestashop-Paxi-Pep-Send/archive/refs/heads/main.zip)

Current release: **v1.3.1**

> After downloading, extract the repository ZIP and upload/re-zip the **`pepstoredelivery`** folder for PrestaShop.

[View the module files](https://github.com/BaloyiTK/Prestashop-Paxi-Pep-Send/tree/main/pepstoredelivery)

## Features

- One PAXI / PEP courier option in checkout
- PAXI service/rate dropdown
- Required preferred PEP/PAXI pickup-store field
- Server-side delivery-step validation via `actionValidateStepComplete`
- Client-side validation for immediate feedback
- Selected PAXI rate is used as the carrier shipping cost
- Pickup service and store are saved against the cart/order
- Order confirmation and admin order display include the selected service and pickup store
- Migration support from older multi-carrier versions
- Safe uninstall cleanup

## Included PAXI services

| Service | Rate |
| --- | ---: |
| Standard Bag ≤5 kg | R59.95 |
| Large Bag ≤10 kg | R109.95 |
| Speed Standard Bag ≤5 kg | R109.95 |
| Speed Large Bag ≤10 kg | R139.95 |

## Requirements

- PrestaShop 8.0 or newer

## Install

1. Click **Download latest version (ZIP)** above.
2. Extract the downloaded repository archive.
3. Zip the `pepstoredelivery` directory so that `pepstoredelivery.php` is inside that directory in the archive.
4. In PrestaShop, go to **Modules > Module Manager > Upload a module**.
5. Upload and install the ZIP.
6. Clear the PrestaShop cache.
7. Test checkout by selecting **PAXI / PEP Courier**, selecting a service, and entering a preferred pickup store.

## Validation

When PAXI / PEP Courier is selected, both the service and preferred pickup store are mandatory. Server-side checkout validation prevents moving to payment when either value is missing.

## Author

Kaziara Solutions
