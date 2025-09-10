#!/bin/bash

# Setup script for Cornerfield cron jobs
# Run this script once to setup automated profit distribution

echo "Setting up Cornerfield cron jobs..."

# Create logs directory
mkdir -p ../logs
chmod 755 ../logs

# Add cron job for daily profit distribution
CRON_JOB="0 0 * * * /usr/bin/php $(pwd)/../cron/daily-profits.php >> $(pwd)/../logs/cron.log 2>&1"

# Check if cron job already exists
if ! crontab -l 2>/dev/null | grep -q "daily-profits.php"; then
    # Add the cron job
    (crontab -l 2>/dev/null; echo "$CRON_JOB") | crontab -
    echo "✅ Daily profit distribution cron job added"
    echo "   Runs every day at midnight (00:00)"
    echo "   Logs saved to: ../logs/daily-profits.log"
else
    echo "⚠️  Cron job already exists"
fi

# Create sample .env updates
echo ""
echo "📝 Add these to your .env file:"
echo "CRYPTOMUS_MERCHANT_ID=your_merchant_id"
echo "CRYPTOMUS_API_KEY=your_api_key"
echo "NOWPAYMENTS_API_KEY=your_nowpayments_key"
echo "SITE_URL=http://localhost/cornerfield"

echo ""
echo "🚀 Setup complete! Your platform now supports:"
echo "   ✅ Automated daily profit distribution"
echo "   ✅ Deposit system (ready for payment gateway integration)"
echo "   ✅ Withdrawal system with admin approval"
echo "   ✅ Referral program with commission tracking"

echo ""
echo "🔧 Next steps:"
echo "   1. Configure payment gateway APIs in .env"
echo "   2. Test deposit/withdrawal flows"
echo "   3. Verify cron job is running: crontab -l"
echo "   4. Monitor logs: tail -f ../logs/daily-profits.log"