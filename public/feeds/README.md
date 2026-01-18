# Google Merchant Center Product Feeds

## Files

### google-merchant-products.tsv
Main product feed for Google Merchant Center. Contains all shipping containers for sale.

**Upload to:** Google Merchant Center > Products > Feeds > Add feed

**Settings:**
- Country: Canada
- Language: English
- Feed type: Primary feed
- File format: Tab-separated values (TSV)

### How to Update

1. Edit `google-merchant-products.tsv` with new products or price changes
2. Deploy the site: `npm run deploy:prod`
3. In Merchant Center, go to Feeds and click "Fetch now" to refresh

## Feed URL

After deployment, the feed is available at:
```
https://ccansam.com/feeds/google-merchant-products.tsv
```

You can use this URL for scheduled fetches in Merchant Center.

## Return Policy Setup

In Merchant Center, go to **Settings > Return policies** and create:

- **Policy name:** inspect_before_delivery
- **Countries:** Canada
- **Return window:** Not accepted after delivery
- **Conditions:** "Purchaser must inspect and accept container before delivery. If refused upon delivery, customer pays delivery, return charges, and a 20% restocking fee."

## Shipping Setup

In Merchant Center, go to **Settings > Shipping and returns** and create:

- **Service name:** free_local
- **Countries:** Canada
- **Delivery time:** 1-5 business days
- **Rate:** Free (within 50km of Saskatoon, SK)
- **Note:** Add a second shipping service for areas outside 50km with "Contact for quote" or a flat rate.
