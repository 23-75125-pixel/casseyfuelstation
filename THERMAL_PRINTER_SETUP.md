# 🖨️ Thermal Printer Setup — GasPOS

Connect a thermal receipt printer to GasPOS for printing transaction receipts after each sale.

---

## 📋 Compatible Printers

GasPOS uses browser-based printing (`window.print()`), so it works with **any printer your OS can see**, including:

| Brand | Popular Models | Connection |
|-------|---------------|------------|
| **Epson** | TM-T82, TM-T88, TM-T20 | USB / LAN / Bluetooth |
| **Xprinter** | XP-58, XP-80, XP-Q200 | USB / LAN |
| **HOIN** | HOP-H58, HOP-H80 | USB |
| **Star Micronics** | TSP143, TSP654 | USB / LAN / Bluetooth |
| **Bixolon** | SRP-330, SRP-350 | USB / LAN |
| **Generic 58mm/80mm** | Any ESC/POS printer | USB |

> **Note:** Both **58mm** and **80mm** paper widths are supported. Configure this in GasPOS Settings.

---

## 🐧 Linux Setup (Ubuntu / Debian)

### Step 1: Install CUPS (Print Server)

```bash
sudo apt update
sudo apt install cups cups-client
sudo systemctl enable cups
sudo systemctl start cups
```

### Step 2: Add Your User to the Printer Group

```bash
sudo usermod -aG lpadmin $USER
```

Log out and log back in for the group change to take effect.

### Step 3: Connect the Printer

- **USB:** Plug in the printer. Linux should detect it automatically.
- **Network/LAN:** Connect the printer to the same network. Find its IP from the printer's settings menu (usually printed on a self-test receipt — hold the feed button while powering on).

### Step 4: Add the Printer in CUPS

1. Open the CUPS web interface: **http://localhost:631**
2. Go to **Administration → Add Printer**
3. Select your printer from the list (USB printers appear as "Local Printers")
   - For network printers, choose **AppSocket/HP JetDirect** and enter: `socket://PRINTER_IP:9100`
4. Choose the driver:
   - If your model is listed, use the specific driver
   - Otherwise, select **Generic → Generic Text-Only Printer** or **Raw Queue**
5. Name it something recognizable (e.g., `ThermalReceipt`)

### Step 5: Set as Default Printer

```bash
# List all printers
lpstat -p -d

# Set default
sudo lpadmin -d ThermalReceipt
```

### Step 6: Test Print

```bash
echo "Hello from GasPOS!" | lp
```

---

## 🪟 Windows Setup

### Step 1: Install the Printer Driver

1. Download the driver from your printer manufacturer's website
2. Connect the printer via USB
3. Run the driver installer
4. The printer should appear in **Settings → Printers & Scanners**

### Step 2: Set as Default

1. Go to **Settings → Printers & Scanners**
2. Turn OFF "Let Windows manage my default printer"
3. Click your thermal printer → **Manage → Set as default**

### Step 3: Adjust Paper Size (important!)

1. Right-click the printer → **Printing Preferences**
2. Set paper size to:
   - **58mm × continuous** (for 58mm printers)
   - **80mm × continuous** (for 80mm printers)
3. If your printer driver has a "Roll Paper" option, enable it

---

## ⚙️ GasPOS Configuration

### Set Thermal Width

1. Log in as **Admin**
2. Go to **Settings** (⚙️ icon in sidebar)
3. Under **Receipt Settings**, set **Thermal Width** to match your printer:
   - `58mm` — mini/portable printers
   - `80mm` — standard countertop printers

### Customize Receipt Content

In the same **Receipt Settings** section:

| Field | Description | Example |
|-------|-------------|---------|
| **Receipt Header** | Text shown at top of receipt | `Thank you for your purchase!` |
| **Receipt Footer** | Text shown at bottom of receipt | `Please come again!` |
| **Business Name** | Your gas station name | `Cassey Fuel Station` |
| **Business Address** | Station address | `123 Main St, City` |
| **Business TIN** | Tax ID number | `123-456-789-000` |

> Business info is configured in the **Business Settings** section on the same page.

---

## 🧾 How Printing Works

1. Complete a sale in the **POS**
2. The **Receipt Preview** modal appears automatically
3. Click **🖨️ Print**
4. GasPOS opens a print-optimized popup window formatted for thermal paper
5. Your browser sends it to the default printer

### Receipt includes:
- Business name, address, TIN
- Transaction number & date
- Customer name & vehicle plate (if entered)
- Itemized list (fuel & oil products)
- Subtotal, discount, tax
- Total, payment amount, change
- Custom header/footer text

---

## 💡 Tips & Tricks

### Skip the Print Dialog (Faster Printing)

**Chrome/Chromium — Kiosk Printing:**

Launch Chrome with the `--kiosk-printing` flag to send print jobs directly to the default printer without showing the dialog:

```bash
google-chrome --kiosk-printing http://localhost:8080
```

**Firefox:**

1. Go to `about:config`
2. Set `print.always_print_silent` to `true`
3. Prints will go directly to the default printer

### Auto-Print After Every Sale

If you want receipts to print automatically without clicking the Print button, you can enable this by adding a small script. Contact your developer for this customization.

### Cash Drawer

If your thermal printer has a **cash drawer port** (RJ-11 kick connector), the drawer will auto-open when a receipt prints — most thermal printers send the drawer kick command automatically. Check your printer settings in CUPS or the manufacturer's utility.

---

## 🔧 Troubleshooting

| Problem | Solution |
|---------|----------|
| Printer not detected (USB) | Try `lsusb` to check if the device appears. Install `usb-modeswitch` if needed. |
| CUPS says "Idle - Accepting Jobs" but nothing prints | Check `sudo tail -f /var/log/cups/error_log` for errors. |
| Receipt is too wide / cut off | Change **Thermal Width** in GasPOS Settings (58mm vs 80mm). |
| Garbled characters printing | Your driver may not support the printer. Try using a **Raw** or **Generic Text** queue. |
| Network printer not found | Verify the printer IP: `ping PRINTER_IP`. Check that port 9100 is open: `nc -zv PRINTER_IP 9100`. |
| Print dialog keeps appearing | Use kiosk printing mode (see Tips above). |
| Receipt prints but very faint | Thermal paper may be loaded upside down. The shiny/smooth side should face the print head. |
| Browser blocks the print popup | Allow popups for your GasPOS domain in browser settings. |

---

## 📎 Quick Reference

```
Connect printer → Install in OS (CUPS/Windows) → Set as default →
Configure width in GasPOS Settings → Complete a sale → Click Print
```

That's it! Your thermal printer is ready to use with GasPOS. 🎉
