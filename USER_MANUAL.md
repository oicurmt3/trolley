# User Manual -- WA Trolley Report

**Website:** [https://sandgroper.net/trolley/](https://sandgroper.net/trolley/)


## What Is This?

WA Trolley Report lets you report abandoned shopping trolleys you find around Western Australia. After reporting, you can optionally email the trolley's owner (e.g., Coles, Woolworths) and your local council to request collection.

All reports are visible on a public map and list so the community can see where abandoned trolleys are piling up.

**This is not an official service.** It is not run by or affiliated with any supermarket, retailer, or council.


## How to Report a Trolley

### Step 1: Go to the Trolley

You must be physically at the same location as the trolley. The app uses your phone's GPS to record the trolley's position, so it will capture wherever you are standing, not where you type from.

### Step 2: Get the Location

Open [https://sandgroper.net/trolley/](https://sandgroper.net/trolley/) on your phone and tap **"Get Trolley Location"**.

Your phone will ask for permission to share your location. You must allow this for the app to work.

Once permission is granted, the app displays your coordinates (latitude and longitude) and the current date/time.

**Important:** Do not use this from your home or any location you do not want to share publicly. The GPS coordinates you submit are visible to everyone on the public map and list.

### Step 3: Select the Brand(s)

Tap the brand of the trolley you found. You can select multiple brands if there are trolleys from different shops in the same spot.

Supported brands: **Coles**, **Woolworths**, **Aldi**, **IGA**, **Kmart**, **Big W**, **Bunnings**, **Spudshed**, **Other**.

When you select a brand, a condition section appears below it.

### Step 4: Select the Condition (Optional)

For each brand you selected, you can describe the trolley's condition. Options include:

- **Good** -- structurally fine, just in the wrong place
- **Needs Cleaning** -- dirty but otherwise OK
- **Rusty** -- visible rust on the frame
- **Damaged Wheel** -- wheel(s) broken, stuck, or missing
- **Bent Frame** -- frame is deformed
- **Broken Handle** -- push handle is damaged
- **Child Seat** -- child seat is broken or stuck
- **Other** -- some other kind of damage

You can select multiple conditions per brand.

### Step 5: Add Comments (Optional)

Type any extra details in the comments box. For example: "Behind the bus stop near Shelley Bridge" or "Three trolleys stacked together". Maximum 200 characters.

### Step 6: Submit

Tap **"Submit Now"**. The app sends your report to the server. If successful, you will see a green confirmation message.


## Emailing the Report

After a successful submission, a popup appears asking if you want to email the report.

### Email Brand Owners

The popup shows an **"Email [Brand]"** button for each brand you reported. Tapping this opens your phone's email app with a pre-filled email addressed to that brand's customer service.

### Email Your Local Council (LGA)

If the app could determine which local council area the trolley is in, an **"Email [Council Name]"** button also appears. Tapping it opens an email to your council.

### If the Email App Does Not Open

Some phones do not open the email app from web links. In that case, the popup shows:

- The recipient email address
- A **Subject** field with a "Copy" button
- A **Body** field with a "Copy" button

You can copy these and paste them into your email app manually.

### Finishing Up

Tap **"No, Thanks / All Done"** to close the popup. Your report has already been saved regardless of whether you send the email.


## Viewing Reports

### List View

Navigate to [view_logs.php](https://sandgroper.net/trolley/view_logs.php) or tap the **"List"** button on the report form.

The list shows all submitted reports in a table with three columns:

- **Brand** -- coloured buttons showing which brands were reported (abbreviated: CO = Coles, WO = Woolworths, etc.)
- **Date** -- when the report was submitted
- **Location** -- suburb and state, linked to Google Maps

**Sorting:** Tap the "Brand" or "Date" column header to sort ascending or descending.

**Details:** Tap a brand button in any row to expand the detail panel showing the trolley's condition and any comments.

**Pagination:** Use the Previous/Next buttons at the bottom to navigate through pages (30 entries per page).

### Map View

Navigate to [map_view.html](https://sandgroper.net/trolley/map_view.html) or tap the **"Map"** button on the report form.

The map shows all reported trolleys as markers. When many trolleys are close together, they are grouped into numbered clusters. Tap a cluster to zoom in. Tap an individual marker to see the report details.

### Brand-Specific Maps

From the map view, you can tap brand buttons (CO, WO, AL, IG, KM, BI, BU, SP, Other) to see a map filtered to only that brand's reports.


## Rate Limits

To prevent misuse, you can submit a maximum of **20 reports per 12 hours**. After 12 hours, the counter resets.


## Privacy

- **No account required** -- you do not need to sign up or log in
- **No personal data collected** -- the app does not ask for your name, email, or phone number
- **GPS coordinates are public** -- the location you submit is visible to everyone on the map and list
- **Do not submit from your home** -- use the app only when you are standing next to the trolley

For full details, see the [Privacy Policy](https://sandgroper.net/trolley/privacy.html).


## Frequently Asked Questions

**Q: Does reporting here get the trolley collected?**
No. This adds the report to the public log and map. The optional email feature lets you contact the owner or council, but collection is not guaranteed.

**Q: Can I delete a report?**
There is no self-service delete. Contact info@sandgroper.net with the approximate date, time, and location of the report.

**Q: Why does it say "Geolocation not supported" or "Permission denied"?**
Your browser must support the Geolocation API and you must allow location access. Ensure you are using HTTPS (not HTTP) and that location services are enabled on your device.

**Q: The suburb or council name is wrong. What happened?**
The suburb and council are determined by a third-party geocoding service based on your GPS coordinates. GPS accuracy varies by device and environment.

**Q: Why is the email showing the wrong council?**
The suburb-to-council mapping currently covers the Perth metropolitan area. If you are outside this area, the council may not be identified correctly or at all.


## Contact

For questions, corrections, or removal requests: **info@sandgroper.net**


## Links

- **Report Form:** [https://sandgroper.net/trolley/](https://sandgroper.net/trolley/)
- **List View:** [https://sandgroper.net/trolley/view_logs.php](https://sandgroper.net/trolley/view_logs.php)
- **Map View:** [https://sandgroper.net/trolley/map_view.html](https://sandgroper.net/trolley/map_view.html)
- **Privacy Policy:** [https://sandgroper.net/trolley/privacy.html](https://sandgroper.net/trolley/privacy.html)
- **Terms & Conditions:** [https://sandgroper.net/trolley/terms.html](https://sandgroper.net/trolley/terms.html)
- **FAQ:** [https://sandgroper.net/trolley/faq.html](https://sandgroper.net/trolley/faq.html)
- **Android App:** [https://sandgroper.net/trolley/trolleyfinder.apk](https://sandgroper.net/trolley/trolleyfinder.apk)
