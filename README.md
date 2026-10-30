# SwiftTrackGo - Live Chat & Reviews Implementation

## Summary

Successfully implemented both requested features for SwiftTrackGo:

### ✅ Live Chat System
- **Built-in chat widget** on all public pages (bottom-right floating button)
- **Green dot indicator** showing support staff online/offline status
- **No email required** - visitors start and resume conversations with a shipment tracking number
- **Admin dashboard** (`/admin/chat.php`) for managing conversations
- **Simple toggle** in admin settings (`/admin/settings.php`)
- **Real-time messaging** with auto-refresh polling (5-second intervals)
- **Cross-device continuation** - returning visitors can reopen a conversation with its tracking number

### ✅ Customer Reviews Section
- **Added to homepage** displaying customer testimonials
- **Three review cards** shown on the homepage
- **Demo examples are visibly marked** and are not represented as verified endorsements
- **Star ratings** displayed alongside each review
- **Visitor review submission** with optional reviewer photo and no email address required
- **Admin approval, hide, and delete controls** at `/admin/reviews.php`
- **New submissions are private until approved**
- **Responsive grid layout** (3 columns desktop, 1 mobile)
- **Clean, professional design** consistent with site branding

## Technical Implementation

### Files Modified/Created

#### Core System
- `includes/functions.php` - Updated chat defaults in `getSiteSettings()`
- `includes/admin_header.php` - Added Live Chat navigation link
- `includes/footer.php` - Built-in chat widget injection
- `index.php` - Added reviews section to homepage
- `assets/css/style.css` - Chat widget and reviews styling

#### Database
- `database/swifttrackgo.sql` - Updated schema with:
  - `chat_conversations` table for visitor sessions
  - `chat_messages` table for message history
  - `reviews` table with 12 seeded reviews
  - Updated `site_settings` with new chat settings

#### API & Admin
- `api/chat.php` - Backend API endpoint for chat operations
- `admin/chat.php` - Complete admin conversation management interface
- `admin/settings.php` - Settings.php already had the simplified live chat toggle
- `admin/reviews.php` - Admin moderation for visitor-submitted reviews
- `database/migrations/20261029_review_photos.sql` - Adds optional reviewer image paths to an existing database

### Key Features

#### Live Chat Widget
- **Bubble button** in bottom-right corner with online/offline indicator
- **Start or resume chats** by tracking number
- **Conversation view** with message history
- **Auto-refresh** for new messages every 5 seconds
- **Session storage** to reconnect returning visitors
- **Admin controls** to toggle support status and close conversations
- **Shipment association** so staff can identify chats by tracking number

#### Reviews Section
- **Review cards** with reviewer name, location, rating, and testimonial
- **Star display** (★☆) showing rating out of 5
- **Responsive design** working on all screen sizes
- **Sorted by rating and creation date** for optimal display

### User Experience

#### Public Side
- **No registration required** to use live chat
- **Quick access** via floating widget on every page
- **Clear status indicators** showing when support is available
- **Mobile-friendly** chat interface

#### Admin Side
- **Full conversation management** from single dashboard
- **Unread message counts** for quick prioritization
- **Online/offline toggle** to control availability
- **Conversation closing** capability

## Database Schema

### New Tables

#### `chat_conversations`
- `id` - Primary key
- `visitor_name` - Visitor's name (no email)
- `visitor_session` - Session identifier for persistence
- `status` - 'active' or 'closed'
- `created_at`, `updated_at` - Timestamps

#### `chat_messages`
- `id` - Primary key
- `conversation_id` - Foreign key to conversations
- `sender_type` - 'visitor' or 'agent'
- `message` - Message content
- `is_read` - Read status for visitors
- `created_at` - Timestamp

#### `reviews`
- `id` - Primary key
- `reviewer_name` - Name of reviewer
- `reviewer_location` - Location of reviewer
- `rating` - 1-5 star rating
- `review_text` - Testimonial content
- `reviewer_image_path` - Optional uploaded reviewer photo path
- `is_active` - Visibility flag
- `is_sample` - Marks seeded examples that must not be presented as verified customer feedback
- `sort_order` - Display order
- `created_at` - Timestamp

### Seeded Reviews
12 demo reviews from locations including:
- New York, USA
- Lagos, Nigeria
- London, UK
- Mexico City, Mexico
- Mumbai, India
- Sydney, Australia
- Dubai, UAE
- Dublin, Ireland
- Singapore
- Toronto, Canada
- Paris, France
- Tokyo, Japan

The seeded testimonials are demo examples, not verified endorsements. The homepage labels them as samples, and the admin cannot approve them as genuine customer reviews. Replace or delete these examples and approve real visitor submissions before launch.

## How It Works

### 1. Chat Flow
1. Visitor clicks chat bubble
2. If no active conversation, shows start chat form
3. Visitor enters a shipment tracking number and optional message
4. System creates or resumes that shipment's persistent conversation
5. Visitor sees conversation view
6. Support admin can reply from `/admin/chat.php`
7. Real-time updates for both parties
8. Visitors can submit reviews on the homepage; new reviews remain private until approved in `/admin/reviews.php`

### 2. Reviews Display
1. System fetches active reviews from database
2. Reviews are sorted by rating and creation date
3. Reviews are displayed in responsive grid
4. Star ratings provide quick visual feedback

## Configuration

### Environment Variables (`.env`)
- `APP_NAME="SwiftTrackGo"`
- `APP_URL="http://localhost/swifttrackgo"`
- `APP_ENV="development"`
- `DB_HOST="localhost"`
- `DB_NAME="swifttrackgo"`
- `DB_USER="root"`
- `DB_PASS=""`

### Admin Settings
- **Enable/Disable chat** in `/admin/settings.php`
- **Toggle online status** from `/admin/chat.php`
- **Manage conversations** from `/admin/chat.php`
- **Moderate customer reviews** from `/admin/reviews.php`

For an existing database created before review moderation was added, import `database/migrations/20261029_review_samples.sql` once. New databases already include the column in `database/swifttrackgo.sql`.

For an existing database, also import `database/migrations/20261029_review_photos.sql` once to enable reviewer photos. Visitors can upload their own optional photo with a review (JPG, PNG, GIF, or WebP, up to 2 MB). Only use genuine customer-approved photos; the demo reviews do not have real portraits.

Apply `database/migrations/20261030_chat_tracking_numbers.sql` once to an existing database to associate chat conversations with shipments. New databases already include this relationship in `database/swifttrackgo.sql`. Conversations created before this migration remain available to staff but are not attached to a shipment. Chat access uses the tracking number alone; anyone who knows it can read and send messages, so visitors should not share sensitive information in chat.

Apply `database/migrations/20261030_chat_tracking_numbers.sql` once to an existing database so chat conversations can be associated with shipments. New databases already include this relationship in `database/swifttrackgo.sql`. Visitors can reopen the same conversation from another device by entering its tracking number. Anyone with that number can access the chat, so visitors should not share sensitive information there.

## Future Enhancements

1. **Message threading** for multi-message conversations
2. **File upload support** for attachments
3. **Email notifications** for new messages (if requested)
4. **Admin chat personalization** (custom greetings)
6. **Social sharing** for reviews

## Testing

To verify the system works:

1. **Start MySQL** and ensure the `swifttrackgo` database exists
2. **Import the database** using: `mysql -u root < database/swifttrackgo.sql`
3. **Navigate to the homepage** - reviews should be visible
4. **Click the chat bubble** - widget should appear
5. **Start or continue a conversation** by entering a shipment tracking number
6. **Access `/admin/chat.php`** to set support online and reply to conversations
7. Submit a review from the homepage and approve it from `/admin/reviews.php`

## Support

For issues with the implementation:
- Check database connection in `config/database.php`
- Verify the `swifttrackgo` database is created and populated
- Ensure PHP MySQL extensions are enabled
- Check browser console for JavaScript errors

## License

All code follows the existing SwiftTrackGo codebase conventions and patterns.