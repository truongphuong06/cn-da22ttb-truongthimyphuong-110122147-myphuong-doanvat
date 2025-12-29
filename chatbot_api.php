<?php
// chatbot_api.php
// Backend PHP: kiểm tra whitelist + gọi Google Gemini (MIỄN PHÍ) + trả JSON

// --------------- CẤU HÌNH ---------------
$GEMINI_API_KEY = "AIzaSyDsQXfUUISFDMbvgwVkcXn1brEHvE7Xyr8"; // <<=== THAY API KEY TỪ https://makersuite.google.com/app/apikey
$GEMINI_URL = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $GEMINI_API_KEY;
$MAX_TOKENS = 800;
$TEMPERATURE = 0.6;

// --------------- PHẠM VI CHO PHÉP (WHITELIST) ---------------
// Sửa danh sách này theo cửa hàng của bạn
$allowedKeywords = [
    "áo","quần","đầm","váy","giày","size","giá","giảm","sale",
    "chất liệu","vải","màu","kích thước","đổi","trả","bảo hành",
    "thanh toán","vận chuyển","giao","hàng","hủy","đơn","phí","ship",
    "sản phẩm","mã","sku","tồn","kho","còn","hết","có","bao","nhiêu",
    "shop","cửa hàng","mua","bán","xem","tư vấn","hỏi","giúp","thông tin",
    "nam","nữ","nu","sơ mi","so mi","thun","khoác","khoac","jean","kaki",
    "len","phông","tay ngắn","ao","quan","vay","dam",
    "phụ kiện","phu kien","túi","tui","giay","dép","dep","mũ","mu","nón","non",
    "thắt lưng","that lung","kính","kinh","glasses","đồng hồ","dong ho","watch",
    "trang sức","trang suc","nhẫn","nhan","vòng","vong","dây chuyền","day chuyen",
    "ví","vi","wallet","ba lô","ba lo","balo","cặp","cap","backpack",
    "khăn","khan","scarf","găng tay","gang tay","gloves","vớ","vo","tất","tat","socks",
    "nơ","no","cài tóc","cai toc","kẹp tóc","kep toc","accessories","bag","shoes","hat","belt"
];
// Nếu shop bạn có từ đặc trưng khác, thêm vào đây (ví dụ: "áo khoác len", "size EU")

// Pattern SKU (nếu bạn có mã sản phẩm có form cố định)
$allowedSkuPattern = "/[A-Z0-9]{3,}-?[0-9]{1,6}/i"; // chỉnh nếu cần

// --------------- HEADERS & INPUT ---------------
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");

$body = json_decode(file_get_contents("php://input"), true);
$userMessage = trim($body["message"] ?? "");
$userId = $body["userId"] ?? null;

if (!$userMessage) {
    http_response_code(400);
    echo json_encode(["error" => "No message"]);
    exit;
}

// Lấy thông tin khách hàng nếu có userId
$userInfo = null;
if ($userId) {
    try {
        $stmt = $conn->prepare("SELECT ten_dang_nhap, ho_ten, email FROM nguoi_dung WHERE id = ?");
        $stmt->execute([$userId]);
        $userInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching user info: " . $e->getMessage());
    }
}

// --------------- KIỂM TRA WHITELIST NỚI LỎNG HƠN ---------------
function containsAllowedKeyword($text, $allowedKeywords, $skuPattern=null) {
    // Nếu câu hỏi dưới 3 từ thì cho qua (thường là câu hỏi ngắn về shop)
    if (str_word_count($text, 0, 'àáảãạăắằẳẵặâấầẩẫậèéẻẽẹêềếểễệìíỉĩịòóỏõọôốồổỗộơớờởỡợùúủũụưứừửữựỳýỷỹỵđ') <= 3) {
        return true;
    }
    
    $t = mb_strtolower($text, "UTF-8");
    if ($skuPattern && preg_match($skuPattern, $text)) return true;
    foreach ($allowedKeywords as $kw) {
        if (mb_stripos($t, mb_strtolower($kw, "UTF-8")) !== false) return true;
    }
    return false;
}

// Nếu không chứa từ khóa nào trong phạm vi -> trả lời từ chối (không gọi API)
if (!containsAllowedKeyword($userMessage, $allowedKeywords, $allowedSkuPattern ?? null)) {
    $reply = "Xin lỗi, tôi chỉ hỗ trợ tư vấn về sản phẩm và dịch vụ của cửa hàng (ví dụ: sản phẩm, giá, size, chất liệu, đổi trả, thanh toán, vận chuyển). Vui lòng đặt câu hỏi liên quan đến shop.";
    echo json_encode(["allowed" => false, "reply" => $reply]);
    exit;
}

// --------------- SYSTEM PROMPT (ÉP model Ở PHẠM VI) ---------------
// Đọc câu trả lời từ database FAQ + Truy vấn sản phẩm thực tế
require_once 'connect.php';

try {
    $lowerMsg = mb_strtolower($userMessage, "UTF-8");
    
    // ============ KIỂM TRA CÂU HỎI VỀ SẢN PHẨM CỤ THỂ ============
    
    // Trích xuất loại sản phẩm và size
    $product_type = null;
    $requested_size = null;
    $gender = null; // Thêm biến giới tính
    
    // Tìm giới tính
    if (preg_match('/\b(nam|boy|men)\b/ui', $userMessage)) {
        $gender = 'nam';
    } elseif (preg_match('/\b(nữ|nu|nư|girl|women|woman)\b/ui', $userMessage)) {
        $gender = 'nữ';
    }
    
    // Tìm loại sản phẩm với chi tiết hơn
    if (preg_match('/(phụ kiện|phu kien|accessories)/ui', $userMessage)) {
        $product_type = 'phụ kiện';
    } elseif (preg_match('/(túi xách|tui xach|bag)/ui', $userMessage)) {
        $product_type = 'túi';
    } elseif (preg_match('/(giày dép|giày|giay|dép|dep|shoes)/ui', $userMessage)) {
        $product_type = 'giày';
    } elseif (preg_match('/(mũ|mu|nón|non|hat|cap)/ui', $userMessage)) {
        $product_type = 'mũ';
    } elseif (preg_match('/(thắt lưng|that lung|dây lưng|belt)/ui', $userMessage)) {
        $product_type = 'thắt lưng';
    } elseif (preg_match('/(kính mắt|kinh mat|kính|kinh|glasses|sunglasses)/ui', $userMessage)) {
        $product_type = 'kính';
    } elseif (preg_match('/(đồng hồ|dong ho|watch)/ui', $userMessage)) {
        $product_type = 'đồng hồ';
    } elseif (preg_match('/(trang sức|trang suc|nhẫn|nhan|vòng|vong|dây chuyền|day chuyen|jewelry)/ui', $userMessage)) {
        $product_type = 'trang sức';
    } elseif (preg_match('/(ví tiền|vi tien|ví|vi|wallet)/ui', $userMessage)) {
        $product_type = 'ví';
    } elseif (preg_match('/(ba lô|ba lo|balo|cặp sách|cap sach|backpack)/ui', $userMessage)) {
        $product_type = 'ba lô';
    } elseif (preg_match('/(khăn choàng|khan choang|khăn quàng|khan quang|khăn|khan|scarf)/ui', $userMessage)) {
        $product_type = 'khăn';
    } elseif (preg_match('/(găng tay|gang tay|gloves)/ui', $userMessage)) {
        $product_type = 'găng tay';
    } elseif (preg_match('/(vớ|vo|tất|tat|socks)/ui', $userMessage)) {
        $product_type = 'vớ';
    } elseif (preg_match('/(nơ|no|cài tóc|cai toc|kẹp tóc|kep toc|hairpin)/ui', $userMessage)) {
        $product_type = 'nơ';
    } elseif (preg_match('/(áo sơ mi|ao so mi|sơ mi|so mi)/ui', $userMessage)) {
        $product_type = 'áo sơ mi';
    } elseif (preg_match('/(áo thun|áo phông|ao thun|áo tay ngắn)/ui', $userMessage)) {
        $product_type = 'áo thun';
    } elseif (preg_match('/(áo khoác|ao khoac|jacket)/ui', $userMessage)) {
        $product_type = 'áo khoác';
    } elseif (preg_match('/(áo len|sweater)/ui', $userMessage)) {
        $product_type = 'áo len';
    } elseif (preg_match('/(váy|đầm|dam|vay|dress)/ui', $userMessage)) {
        $product_type = 'váy';
        $gender = 'nữ'; // Váy/đầm tự động là nữ
    } elseif (preg_match('/(quần jean|jean|jeans)/ui', $userMessage)) {
        $product_type = 'quần jean';
    } elseif (preg_match('/(quần kaki|kaki)/ui', $userMessage)) {
        $product_type = 'quần kaki';
    } elseif (preg_match('/(quần|quan|pants)/ui', $userMessage)) {
        $product_type = 'quần';
    } elseif (preg_match('/(áo|ao|shirt)/ui', $userMessage)) {
        $product_type = 'áo';
    }
    
    // Tìm size - cải thiện regex
    if (preg_match('/size\s*([smlxSMLX]{1,3})/ui', $userMessage, $matches)) {
        $requested_size = strtoupper($matches[1]);
    } elseif (preg_match('/\b([smlxSMLX]{1,3})\b(?!.*\d)/u', $userMessage, $matches)) {
        $requested_size = strtoupper($matches[1]);
    }
    
    // Nếu hỏi về size cụ thể của sản phẩm
    if ($requested_size && $product_type) {
        
        // Bảng size chi tiết theo sản phẩm
        $sizeGuide = [
            'váy' => [
                'S' => ['weight' => '45-50kg', 'height' => '1m50-1m58'],
                'M' => ['weight' => '50-55kg', 'height' => '1m55-1m62'],
                'L' => ['weight' => '55-62kg', 'height' => '1m60-1m68'],
                'XL' => ['weight' => '62-70kg', 'height' => '1m65-1m72'],
                'XXL' => ['weight' => '70-80kg', 'height' => '1m68-1m75']
            ],
            'áo' => [
                'S' => ['weight' => '42-50kg', 'height' => '1m50-1m60'],
                'M' => ['weight' => '50-58kg', 'height' => '1m58-1m65'],
                'L' => ['weight' => '58-65kg', 'height' => '1m62-1m70'],
                'XL' => ['weight' => '65-75kg', 'height' => '1m68-1m75'],
                'XXL' => ['weight' => '75-85kg', 'height' => '1m70-1m78']
            ],
            'quần' => [
                'S' => ['weight' => '45-52kg', 'height' => '1m50-1m60'],
                'M' => ['weight' => '52-60kg', 'height' => '1m58-1m68'],
                'L' => ['weight' => '60-68kg', 'height' => '1m65-1m72'],
                'XL' => ['weight' => '68-78kg', 'height' => '1m68-1m78'],
                'XXL' => ['weight' => '78-88kg', 'height' => '1m70-1m80']
            ]
        ];
        
        if (isset($sizeGuide[$product_type][$requested_size])) {
            $size_info = $sizeGuide[$product_type][$requested_size];
            
            // Kiểm tra còn hàng trong database - có filter giới tính
            $conn_mysqli = new mysqli('localhost', 'root', '', 'ban_hang');
            
            // Tìm kiếm linh hoạt hơn - OR nhiều pattern
            $searchPatterns = [$product_type];
            
            // Thêm các biến thể tìm kiếm
            if ($product_type == 'váy') {
                $searchPatterns = ['váy', 'đầm', 'dam', 'vay'];
            } elseif ($product_type == 'áo sơ mi') {
                $searchPatterns = ['sơ mi', 'so mi'];
            } elseif ($product_type == 'áo thun') {
                $searchPatterns = ['thun', 'phông'];
            } elseif ($product_type == 'phụ kiện') {
                $searchPatterns = ['phụ kiện', 'phu kien'];
            }
            
            // Build query động
            $whereClauses = [];
            $params = [];
            $types = '';
            
            foreach ($searchPatterns as $pattern) {
                $whereClauses[] = "ten_san_pham LIKE ?";
                $params[] = "%" . $pattern . "%";
                $types .= 's';
            }
            
            $searchQuery = "SELECT id, ten_san_pham, gia, hinh_anh, so_luong FROM san_pham WHERE (" . implode(" OR ", $whereClauses) . ") AND so_luong > 0";
            
            // Chỉ filter giới tính cho áo/quần, không filter cho váy/phụ kiện
            if ($gender && !in_array($product_type, ['váy', 'đầm', 'phụ kiện', 'túi', 'giày', 'mũ', 'thắt lưng', 'kính', 'đồng hồ', 'trang sức', 'ví', 'ba lô', 'khăn', 'găng tay', 'vớ', 'nơ'])) {
                $searchQuery .= " AND ten_san_pham LIKE ?";
                $params[] = "%" . $gender . "%";
                $types .= 's';
            }
            
            $searchQuery .= " ORDER BY so_luong DESC LIMIT 5";
            
            $stmt = $conn_mysqli->prepare($searchQuery);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            $products = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            $conn_mysqli->close();
            
            $genderLabel = $gender ? " " . $gender : "";
            
            if (!empty($products)) {
                $reply = "✅ **{$product_type}{$genderLabel} size {$requested_size}** phù hợp với:\n\n";
                $reply .= "👤 **Cân nặng:** {$size_info['weight']}\n";
                $reply .= "📏 **Chiều cao:** {$size_info['height']}\n\n";
                $reply .= "🛍️ **Hiện còn hàng:**\n";
                
                $productList = [];
                foreach ($products as $i => $product) {
                    $price = number_format($product['gia'], 0, ',', '.');
                    $stock_status = $product['so_luong'] > 10 ? "Còn nhiều" : "Còn {$product['so_luong']} sản phẩm";
                    $reply .= ($i + 1) . ". {$product['ten_san_pham']} - {$price}đ ({$stock_status})\n";
                    
                    // Thêm thông tin sản phẩm để gửi hình
                    $productList[] = [
                        'id' => $product['id'],
                        'name' => $product['ten_san_pham'],
                        'price' => $price . 'đ',
                        'image' => 'uploads/' . $product['hinh_anh']
                    ];
                }
                
                $reply .= "\n💡 Bạn muốn đặt mua sản phẩm nào? Hoặc cần tư vấn thêm?";
                
                echo json_encode([
                    "allowed" => true, 
                    "reply" => $reply,
                    "products" => $productList,
                    "showImages" => true
                ]);
                exit;
                $reply = "❌ **{$product_type} size {$requested_size}** hiện **hết hàng**.\n\n";
                $reply .= "📏 Size {$requested_size} phù hợp với:\n";
                $reply .= "- Cân nặng: {$size_info['weight']}\n";
                $reply .= "- Chiều cao: {$size_info['height']}\n\n";
                $reply .= "💡 Bạn có thể:\n";
                $reply .= "✅ Chọn size khác (S, M, L, XL, XXL)\n";
                $reply .= "✅ Đặt hàng trước (về hàng trong 3-5 ngày)\n";
                $reply .= "✅ Xem sản phẩm tương tự";
            }
            
            echo json_encode(["allowed" => true, "reply" => $reply]);
            exit;
        }
    }
    
    // Nếu chỉ hỏi về size mà không chỉ rõ sản phẩm
    if ($requested_size && !$product_type) {
        $reply = "📏 **Size {$requested_size}** của shop phù hợp với:\n\n";
        $reply .= "👕 **Áo:** 50-58kg, cao 1m58-1m65\n";
        $reply .= "👖 **Quần:** 52-60kg, cao 1m58-1m68\n";
        $reply .= "👗 **Váy/Đầm:** 50-55kg, cao 1m55-1m62\n\n";
        $reply .= "💡 Bạn muốn xem size {$requested_size} của sản phẩm nào? (áo/quần/váy)";
        
        echo json_encode(["allowed" => true, "reply" => $reply]);
        exit;
    }
    
    // Nếu hỏi về sản phẩm nhưng không chỉ rõ size
    if ($product_type && !$requested_size) {
        // Kiểm tra còn hàng với filter giới tính
        $conn_mysqli = new mysqli('localhost', 'root', '', 'ban_hang');
        
        // Tìm kiếm linh hoạt hơn
        $searchPatterns = [$product_type];
        
        if ($product_type == 'váy') {
            $searchPatterns = ['váy', 'đầm', 'dam', 'vay'];
        } elseif ($product_type == 'áo sơ mi') {
            $searchPatterns = ['sơ mi', 'so mi'];
        } elseif ($product_type == 'áo thun') {
            $searchPatterns = ['thun', 'phông'];
        } elseif ($product_type == 'phụ kiện') {
            $searchPatterns = ['phụ kiện', 'phu kien'];
        } elseif ($product_type == 'giày') {
            $searchPatterns = ['giày', 'giay', 'dép', 'dep'];
        } elseif ($product_type == 'túi') {
            $searchPatterns = ['túi', 'tui', 'xách', 'xach'];
        } elseif ($product_type == 'mũ') {
            $searchPatterns = ['mũ', 'mu', 'nón', 'non'];
        } elseif ($product_type == 'kính') {
            $searchPatterns = ['kính', 'kinh', 'glass'];
        } elseif ($product_type == 'đồng hồ') {
            $searchPatterns = ['đồng hồ', 'dong ho', 'watch'];
        } elseif ($product_type == 'trang sức') {
            $searchPatterns = ['trang sức', 'trang suc', 'nhẫn', 'nhan', 'vòng', 'vong', 'dây chuyền', 'day chuyen'];
        } elseif ($product_type == 'ví') {
            $searchPatterns = ['ví', 'vi', 'wallet'];
        } elseif ($product_type == 'ba lô') {
            $searchPatterns = ['ba lô', 'ba lo', 'balo', 'cặp', 'cap'];
        } elseif ($product_type == 'khăn') {
            $searchPatterns = ['khăn', 'khan', 'scarf'];
        } elseif ($product_type == 'găng tay') {
            $searchPatterns = ['găng', 'gang', 'glove'];
        } elseif ($product_type == 'vớ') {
            $searchPatterns = ['vớ', 'vo', 'tất', 'tat', 'sock'];
        } elseif ($product_type == 'nơ') {
            $searchPatterns = ['nơ', 'no', 'cài', 'cai', 'kẹp', 'kep'];
        }
        
        $whereClauses = [];
        $params = [];
        $types = '';
        
        foreach ($searchPatterns as $pattern) {
            $whereClauses[] = "ten_san_pham LIKE ?";
            $params[] = "%" . $pattern . "%";
            $types .= 's';
        }
        
        $searchQuery = "SELECT id, ten_san_pham, gia, so_luong FROM san_pham WHERE (" . implode(" OR ", $whereClauses) . ") AND so_luong > 0";
        
        // Chỉ filter giới tính khi tên sản phẩm có từ "nam" hoặc "nữ" rõ ràng
        // Không filter cho váy/đầm/phụ kiện vì mặc định đã rõ hoặc unisex
        if ($gender && !in_array($product_type, ['váy', 'đầm', 'phụ kiện', 'túi', 'giày', 'mũ', 'thắt lưng', 'kính', 'đồng hồ', 'trang sức', 'ví', 'ba lô', 'khăn', 'găng tay', 'vớ', 'nơ'])) {
            $searchQuery .= " AND ten_san_pham LIKE ?";
            $params[] = "%" . $gender . "%";
            $types .= 's';
        }
        
        $searchQuery .= " ORDER BY so_luong DESC LIMIT 5";
        
        $stmt = $conn_mysqli->prepare($searchQuery);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $products = $result->fetch_all(MYSQLI_ASSOC);
        
        // Debug log
        error_log("Chatbot Query - Product Type: $product_type, Gender: " . ($gender ?? 'NULL') . ", Found: " . count($products));
        
        $stmt->close();
        $conn_mysqli->close();
        
        $genderLabel = $gender ? " " . $gender : "";
        
        if (!empty($products)) {
            $reply = "✅ **{$product_type}{$genderLabel}** hiện **còn hàng**!\n\n";
            $reply .= "🛍️ Một số sản phẩm:\n";
            
            $productList = [];
            foreach ($products as $i => $product) {
                $price = number_format($product['gia'], 0, ',', '.');
                $stock_status = $product['so_luong'] > 10 ? "Còn nhiều" : "Còn {$product['so_luong']} cái";
                $reply .= ($i + 1) . ". {$product['ten_san_pham']} - {$price}đ ({$stock_status})\n";
                
                // Lấy hình ảnh từ database
                $conn_img = new mysqli('localhost', 'root', '', 'ban_hang');
                $img_stmt = $conn_img->prepare("SELECT hinh_anh FROM san_pham WHERE id = ?");
                $img_stmt->bind_param("i", $product['id']);
                $img_stmt->execute();
                $img_result = $img_stmt->get_result();
                $img_data = $img_result->fetch_assoc();
                $img_stmt->close();
                $conn_img->close();
                
                $productList[] = [
                    'id' => $product['id'],
                    'name' => $product['ten_san_pham'],
                    'price' => $price . 'đ',
                    'image' => 'uploads/' . ($img_data['hinh_anh'] ?? 'no-image.jpg')
                ];
            }
            
            $reply .= "\n📏 Shop có size: S, M, L, XL, XXL\n";
            $reply .= "💡 Bạn cần size nào?";
            
            echo json_encode([
                "allowed" => true, 
                "reply" => $reply,
                "products" => $productList,
                "showImages" => true
            ]);
            exit;
        } else {
            $reply = "❌ **{$product_type}{$genderLabel}** hiện **tạm hết hàng**.\n\n";
            $reply .= "💡 Bạn có thể:\n";
            if ($gender) {
                $reply .= "✅ Xem {$product_type} " . ($gender == 'nam' ? 'nữ' : 'nam') . "\n";
            }
            $reply .= "✅ Xem sản phẩm khác\n";
            $reply .= "✅ Đặt hàng trước (về trong 3-5 ngày)\n";
            $reply .= "✅ Liên hệ hotline: 1900-xxxx";
            
            echo json_encode(["allowed" => true, "reply" => $reply]);
            exit;
        }
    }
    
    // ============ KIỂM TRA FAQ TRONG DATABASE ============
    // Lấy tất cả FAQ đang active, sắp xếp theo priority
    $stmt = $conn->prepare("SELECT keywords, answer FROM chatbot_faq WHERE is_active = 1 ORDER BY priority DESC");
    $stmt->execute();
    $faqs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $lowerMsg = mb_strtolower($userMessage, "UTF-8");
    $foundAnswer = null;
    $maxMatches = 0;
    
    // Tìm câu trả lời khớp nhiều từ khóa nhất
    foreach ($faqs as $faq) {
        $keywords = explode('|', $faq['keywords']);
        $matches = 0;
        
        foreach ($keywords as $keyword) {
            if (mb_stripos($lowerMsg, trim($keyword)) !== false) {
                $matches++;
            }
        }
        
        if ($matches > $maxMatches) {
            $maxMatches = $matches;
            $foundAnswer = $faq['answer'];
        }
    }
    
    if ($foundAnswer) {
        echo json_encode(["allowed" => true, "reply" => $foundAnswer]);
        exit;
    }
    
    // Nếu không tìm thấy trong FAQ, trả lời mặc định
    $reply = "Xin lỗi, tôi chưa có thông tin về câu hỏi này. 🤔\n\n💡 Bạn có thể hỏi về:\n✅ Giá sản phẩm\n✅ Size và kích thước\n✅ Đổi trả hàng\n✅ Vận chuyển\n✅ Thanh toán\n\nHoặc liên hệ hotline: 1900-xxxx";
    echo json_encode(["allowed" => true, "reply" => $reply]);
    exit;
    
} catch (Exception $e) {
    // Nếu lỗi database, dùng logic cũ
    $lowerMsg = mb_strtolower($userMessage, "UTF-8");
    
    if (mb_stripos($lowerMsg, "chào") !== false || mb_stripos($lowerMsg, "hello") !== false) {
        $reply = "Xin chào! 👋 Tôi là trợ lý ảo của shop. Tôi có thể giúp bạn về sản phẩm, giá cả, size, đổi trả, vận chuyển. Bạn cần hỗ trợ gì?";
    } else {
        $reply = "Tôi có thể giúp bạn về: giá sản phẩm 💰, size 📏, đổi trả 🔄, vận chuyển 🚚, thanh toán 💳. Bạn muốn hỏi về vấn đề nào?";
    }
    echo json_encode(["allowed" => true, "reply" => $reply]);
    exit;
}

// --------------- TRẢ LỜI CỐ ĐỊNH CŨ (ĐÃ THAY BẰNG DATABASE) ---------------
/*
$lowerMsg = mb_strtolower($userMessage, "UTF-8");

if (mb_stripos($lowerMsg, "giá") !== false || mb_stripos($lowerMsg, "bao nhiêu") !== false) {
    $reply = "Giá sản phẩm của shop dao động từ 100.000đ - 500.000đ. Bạn có thể xem chi tiết giá từng sản phẩm trên trang web. Cần tư vấn sản phẩm nào cụ thể không?";
} elseif (mb_stripos($lowerMsg, "size") !== false || mb_stripos($lowerMsg, "kích") !== false) {
    $reply = "Shop có đầy đủ size từ S đến XXL. Bảng size chi tiết: S (45-50kg), M (50-55kg), L (55-60kg), XL (60-70kg), XXL (70-80kg). Bạn cần tư vấn size cho sản phẩm nào?";
} elseif (mb_stripos($lowerMsg, "đổi") !== false || mb_stripos($lowerMsg, "trả") !== false || mb_stripos($lowerMsg, "hoàn") !== false) {
    $reply = "Shop hỗ trợ đổi/trả hàng trong vòng 7 ngày nếu sản phẩm còn nguyên tem mác, chưa qua sử dụng. Phí ship hoàn trả do khách hàng chi trả. Bạn cần hỗ trợ gì thêm?";
} elseif (mb_stripos($lowerMsg, "ship") !== false || mb_stripos($lowerMsg, "giao") !== false || mb_stripos($lowerMsg, "vận chuyển") !== false) {
    $reply = "Shop giao hàng toàn quốc. Phí ship 30.000đ nội thành, 50.000đ ngoại thành. MIỄN PHÍ SHIP cho đơn từ 300.000đ. Thời gian giao hàng 2-3 ngày.";
} elseif (mb_stripos($lowerMsg, "thanh toán") !== false || mb_stripos($lowerMsg, "trả tiền") !== false) {
    $reply = "Shop nhận thanh toán qua: COD (tiền mặt), chuyển khoản ngân hàng, Momo, ZaloPay. Bạn muốn thanh toán theo hình thức nào?";
} elseif (mb_stripos($lowerMsg, "áo") !== false || mb_stripos($lowerMsg, "quần") !== false || mb_stripos($lowerMsg, "đầm") !== false || mb_stripos($lowerMsg, "váy") !== false) {
    $reply = "Shop chuyên quần áo thời trang nam nữ, đa dạng kiểu dáng và màu sắc. Bạn có thể xem các sản phẩm trên trang chủ. Cần tư vấn sản phẩm cụ thể nào không?";
} elseif (mb_stripos($lowerMsg, "chào") !== false || mb_stripos($lowerMsg, "hello") !== false || mb_stripos($lowerMsg, "hi") !== false) {
    $reply = "Xin chào! 👋 Tôi là trợ lý ảo của shop thời trang. Tôi có thể giúp bạn tư vấn về sản phẩm, giá cả, size, đổi trả, vận chuyển. Bạn cần hỗ trợ gì?";
} else {
    $reply = "Tôi có thể giúp bạn về: giá sản phẩm 💰, size 📏, đổi trả 🔄, vận chuyển 🚚, thanh toán 💳. Bạn muốn hỏi về vấn đề nào?";
}

echo json_encode(["allowed" => true, "reply" => $reply]);
exit;

// --------------- CODE DƯỚI ĐÂY CHỈ DÙNG KHI CÓ API KEY HỢP LỆ ---------------
// Nếu không có API key hợp lệ, dùng fallback reply
if (empty($GEMINI_API_KEY) || strpos($GEMINI_API_KEY, 'AIzaSyDsQXfUUISFDMbvgwVkcXn1brEHvE7Xyr8') !== false) {
    // Fallback logic với thông tin user nếu có
    $lowerMsg = mb_strtolower($userMessage, "UTF-8");

    if ($userInfo) {
        $greeting = "Xin chào " . $userInfo['ho_ten'] . "! 👋 ";
    } else {
        $greeting = "Xin chào! 👋 ";
    }

    if (mb_stripos($lowerMsg, "chào") !== false || mb_stripos($lowerMsg, "hello") !== false || mb_stripos($lowerMsg, "hi") !== false || mb_stripos($lowerMsg, "chao") !== false) {
        $reply = $greeting . "Tôi là trợ lý ảo của shop thời trang. Tôi có thể giúp bạn tư vấn về sản phẩm, giá cả, size, đổi trả, vận chuyển. Bạn cần hỗ trợ gì?";
    } elseif (mb_stripos($lowerMsg, "áo") !== false || mb_stripos($lowerMsg, "quần") !== false || mb_stripos($lowerMsg, "đầm") !== false || mb_stripos($lowerMsg, "váy") !== false) {
        $reply = "Shop chuyên quần áo thời trang nam nữ, đa dạng kiểu dáng và màu sắc. Bạn có thể xem các sản phẩm trên trang chủ. Cần tư vấn sản phẩm cụ thể nào không?";
    } elseif (mb_stripos($lowerMsg, "size") !== false || mb_stripos($lowerMsg, "kích") !== false) {
        $reply = "Shop có đầy đủ size từ S đến XXL. Bảng size chi tiết: S (45-50kg), M (50-55kg), L (55-60kg), XL (60-70kg), XXL (70-80kg). Bạn cần tư vấn size cho sản phẩm nào?";
    } elseif (mb_stripos($lowerMsg, "đổi") !== false || mb_stripos($lowerMsg, "trả") !== false || mb_stripos($lowerMsg, "hoàn") !== false) {
        $reply = "Shop hỗ trợ đổi/trả hàng trong vòng 7 ngày nếu sản phẩm còn nguyên tem mác, chưa qua sử dụng. Phí ship hoàn trả do khách hàng chi trả. Bạn cần hỗ trợ gì thêm?";
    } elseif (mb_stripos($lowerMsg, "ship") !== false || mb_stripos($lowerMsg, "giao") !== false || mb_stripos($lowerMsg, "vận chuyển") !== false) {
        $reply = "Shop giao hàng toàn quốc. Phí ship 30.000đ nội thành, 50.000đ ngoại thành. MIỄN PHÍ SHIP cho đơn từ 300.000đ. Thời gian giao hàng 2-3 ngày.";
    } elseif (mb_stripos($lowerMsg, "thanh toán") !== false || mb_stripos($lowerMsg, "trả tiền") !== false) {
        $reply = "Shop nhận thanh toán qua: COD (tiền mặt), chuyển khoản ngân hàng, Momo, ZaloPay. Bạn muốn thanh toán theo hình thức nào?";
    } else {
        $reply = "Tôi có thể giúp bạn về: giá sản phẩm 💰, size 📏, đổi trả 🔄, vận chuyển 🚚, thanh toán 💳. Bạn muốn hỏi về vấn đề nào?";
    }

    echo json_encode(["allowed" => true, "reply" => $reply]);
    exit;
}

/*
$systemPrompt = "
Bạn là chatbot trợ giúp khách hàng cho cửa hàng thời trang (quần áo, giày dép).
Chỉ trả lời những câu hỏi liên quan đến: sản phẩm, giá, size, chất liệu, đổi trả, bảo hành, thanh toán, vận chuyển, mã sản phẩm (SKU), tình trạng tồn kho.
Nếu câu hỏi không liên quan, trả lời ngắn gọn: 'Xin lỗi, tôi chỉ hỗ trợ tư vấn sản phẩm và dịch vụ của shop.'
Trả lời bằng tiếng Việt, lịch sự, ngắn gọn (khoảng 1-5 câu) trừ khi khách yêu cầu chi tiết.
";

// Thêm thông tin khách hàng vào system prompt nếu có
if ($userInfo) {
    $systemPrompt .= "\n\nTHÔNG TIN KHÁCH HÀNG ĐĂNG NHẬP:
- Tên đăng nhập: {$userInfo['ten_dang_nhap']}
- Họ tên: {$userInfo['ho_ten']}
- Email: {$userInfo['email']}

Hãy sử dụng thông tin này để cá nhân hóa phản hồi khi phù hợp.";
}

// --------------- TẬP TIN MESSAGES GỬI LÊN GEMINI ---------------
$payload = [
    "contents" => [
        [
            "parts" => [
                ["text" => $systemPrompt . "\n\nKhách hỏi: " . $userMessage]
            ]
        ]
    ],
    "generationConfig" => [
        "temperature" => $TEMPERATURE,
        "maxOutputTokens" => $MAX_TOKENS
    ]
];

// --------------- GỌI GEMINI ---------------
$ch = curl_init($GEMINI_URL);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json"
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Tắt verify SSL nếu local

$response = curl_exec($ch);
$curlErr = curl_error($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Log để debug
error_log("Gemini Response Code: " . $httpcode);
error_log("Gemini Response: " . $response);

if ($curlErr) {
    http_response_code(500);
    echo json_encode(["allowed" => true, "reply" => "Xin lỗi, hệ thống tạm thời gặp sự cố kết nối. Vui lòng thử lại sau. (Lỗi: " . $curlErr . ")"]);
    exit;
}

if ($httpcode >= 400) {
    $errorDetail = json_decode($response, true);
    $errorMsg = $errorDetail["error"]["message"] ?? "Lỗi không xác định";
    echo json_encode(["allowed" => true, "reply" => "Xin lỗi, tôi không thể xử lý yêu cầu lúc này. Vui lòng thử lại sau. (Mã lỗi: " . $httpcode . ")"]);
    exit;
}

// Parse response - Gemini format
$data = json_decode($response, true);
$botReply = $data["candidates"][0]["content"]["parts"][0]["text"] ?? null;

if ($botReply === null) {
    // Nếu không có reply, trả về message mặc định
    echo json_encode(["allowed" => true, "reply" => "Xin lỗi, tôi không hiểu câu hỏi. Bạn có thể hỏi về sản phẩm, giá cả, size, đổi trả, hoặc vận chuyển."]);
} else {
    echo json_encode(["allowed" => true, "reply" => $botReply]);
}
*/
