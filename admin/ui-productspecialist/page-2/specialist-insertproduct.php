<?php
// specialist-insertproduct.php
// Entry point: boots auth, routes AJAX, handles form submit, then renders the view.

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_PRODUCTSPECIALIST];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

// Shared helpers (convertToWebp, loadCategories)
include ROOT_PATH . '/admin/ui-productspecialist/backend/backend-page-2/specialist-insertproduct-helpers.php';

$success = '';
$error = '';

// ── Category AJAX ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['category_action'])) {
    include ROOT_PATH . '/admin/ui-productspecialist/backend/backend-page-2/specialist-category-handler.php';
    // handler always exits, but just in case:
    exit;
}

// ── Product insert ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_product'])) {
    include ROOT_PATH . '/admin/ui-productspecialist/backend/backend-page-2/specialist-insertproduct-handler.php';
}

// ── Load categories for dropdown ───────────────────────────────────────────
$categories = loadCategories($conn);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Insert Product — NobleHome</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
</head>

<body class="bg-slate-100">
    <?php include ROOT_PATH . '/admin/navigation/navbar.php'; ?>

    <div class="ml-60 min-h-screen bg-slate-100 p-6">

        <!-- Header -->
        <div class="mb-6">
            <p class="text-xs text-gray-400 uppercase tracking-widest mb-1">Product Specialist</p>
            <h1 class="text-xl font-bold text-gray-800">Insert New Product</h1>
            <p class="text-sm text-gray-500 mt-0.5">Fill in each step to add a product with colors and variants.</p>
        </div>

        <!-- Alerts -->
        <?php if ($success): ?>
            <div
                class="flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 text-sm rounded-lg px-4 py-3 mb-6">
                <i class="fa-solid fa-circle-check text-green-500"></i>
                <?= $success ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div
                class="flex items-center gap-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg px-4 py-3 mb-6">
                <i class="fa-solid fa-circle-exclamation text-red-500"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Step Progress Bar -->
        <div class="flex items-center gap-0 mb-8 select-none" id="step-bar">
            <?php
            $steps = ['Product Info', 'Colors', 'Variants', 'Specifications', 'Gallery', 'Review'];
            foreach ($steps as $si => $sLabel):
                $num = $si + 1;
                ?>
                <div class="flex items-center <?= $si < count($steps) - 1 ? 'flex-1' : '' ?>">
                    <div class="step-indicator flex items-center gap-2 cursor-pointer" data-step="<?= $num ?>">
                        <div id="step-circle-<?= $num ?>"
                            class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold transition-all duration-200
                                <?= $num === 1 ? 'bg-amber-500 text-white' : 'bg-white border-2 border-gray-200 text-gray-400' ?>">
                            <?= $num ?>
                        </div>
                        <span id="step-label-<?= $num ?>"
                            class="text-xs font-medium <?= $num === 1 ? 'text-amber-600' : 'text-gray-400' ?>">
                            <?= $sLabel ?>
                        </span>
                    </div>
                    <?php if ($si < count($steps) - 1): ?>
                        <div class="flex-1 h-px mx-3 transition-all duration-300 <?= $num === 1 ? 'bg-amber-200' : 'bg-gray-200' ?>"
                            id="step-line-<?= $num ?>"></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <form method="POST" enctype="multipart/form-data" id="productForm">

            <!-- ░░ STEP 1 — Product Info ░░ -->
            <div id="step-panel-1" class="step-panel">
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 mb-4">
                    <div class="flex items-center gap-3 mb-5 pb-4 border-b border-gray-100">
                        <div class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center">
                            <i class="fa-solid fa-box text-amber-500 text-sm"></i>
                        </div>
                        <div>
                            <h2 class="text-sm font-semibold text-gray-800">Step 1 — Product Information</h2>
                            <p class="text-xs text-gray-400">Basic details of your product.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-5">

                        <!-- Product Name -->
                        <div>
                            <label class="block text-xs font-medium text-gray-600 uppercase tracking-widest mb-1.5">
                                Product Name <span class="text-red-400">*</span>
                            </label>
                            <input type="text" name="product_name" required placeholder="e.g. Classic Oak Dining Table"
                                class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-lg bg-gray-50 focus:outline-none focus:border-amber-400 focus:bg-white transition">
                        </div>

                        <!-- Category + Settings trigger -->
                        <div>
                            <label class="block text-xs font-medium text-gray-600 uppercase tracking-widest mb-1.5">
                                Category <span class="text-red-400">*</span>
                            </label>
                            <div class="flex items-center gap-2">
                                <select name="product_category" id="category-select" required
                                    class="flex-1 px-3 py-2.5 text-sm border border-gray-200 rounded-lg bg-gray-50 focus:outline-none focus:border-amber-400 focus:bg-white transition">
                                    <option value="">Select category…</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= htmlspecialchars($cat['name']) ?>">
                                            <?= htmlspecialchars($cat['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" onclick="openCatPanel()" title="Manage categories"
                                    class="w-10 h-10 shrink-0 flex items-center justify-center rounded-lg border border-gray-200 bg-gray-50 text-gray-400 hover:bg-amber-50 hover:border-amber-300 hover:text-amber-500 transition">
                                    <i class="fa-solid fa-gear text-sm"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="col-span-2">
                            <label class="block text-xs font-medium text-gray-600 uppercase tracking-widest mb-1.5">
                                Description
                            </label>
                            <textarea name="product_description" rows="4" placeholder="Describe the product…"
                                class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-lg bg-gray-50 focus:outline-none focus:border-amber-400 focus:bg-white transition resize-none"></textarea>
                        </div>

                        <!-- Unit -->
                        <div>
                            <label class="block text-xs font-medium text-gray-600 uppercase tracking-widest mb-1.5">
                                Unit <span class="text-red-400">*</span>
                            </label>
                            <input type="text" name="product_unit" required placeholder="e.g. pcs, set, box"
                                class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-lg bg-gray-50 focus:outline-none focus:border-amber-400 focus:bg-white transition">
                        </div>

                        <!-- Product Image -->
                        <div class="col-span-2">
                            <label class="block text-xs font-medium text-gray-600 uppercase tracking-widest mb-1.5">
                                Product Image
                                <span class="text-xs text-gray-400 normal-case font-normal">(will be saved as
                                    .webp)</span>
                            </label>
                            <div class="flex items-start gap-4">
                                <label
                                    class="flex flex-col items-center justify-center w-32 h-32 border-2 border-dashed border-gray-200 rounded-lg cursor-pointer hover:border-amber-400 transition overflow-hidden bg-gray-50"
                                    id="product-img-label">
                                    <img id="product-img-preview" src="" alt=""
                                        class="hidden w-full h-full object-cover rounded-lg">
                                    <div id="product-img-placeholder"
                                        class="flex flex-col items-center justify-center gap-1 text-gray-400">
                                        <i class="fa-solid fa-image text-2xl"></i>
                                        <span class="text-[10px]">Upload image</span>
                                    </div>
                                    <input type="file" name="product_image" accept="image/*" class="hidden"
                                        id="product-img-input">
                                </label>
                                <p class="text-xs text-gray-400 mt-2">Accepted: JPG, PNG, GIF, WebP.<br>Auto-converted
                                    to <strong>.webp</strong>.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="button" onclick="goToStep(2)"
                        class="px-6 py-2.5 bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold rounded-lg transition flex items-center gap-2">
                        Next: Add Colors <i class="fa-solid fa-arrow-right text-xs"></i>
                    </button>
                </div>
            </div>

            <!-- ░░ STEP 2 — Colors ░░ -->
            <div id="step-panel-2" class="step-panel hidden">
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 mb-4">
                    <div class="flex items-center justify-between mb-5 pb-4 border-b border-gray-100">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center">
                                <i class="fa-solid fa-palette text-amber-500 text-sm"></i>
                            </div>
                            <div>
                                <h2 class="text-sm font-semibold text-gray-800">Step 2 — Colors</h2>
                                <p class="text-xs text-gray-400">Add one or more color variants for this product.</p>
                            </div>
                        </div>
                        <button type="button" onclick="addColor()"
                            class="flex items-center gap-2 px-4 py-2 text-xs font-semibold text-amber-600 bg-amber-50 hover:bg-amber-100 rounded-lg transition">
                            <i class="fa-solid fa-plus"></i> Add Color
                        </button>
                    </div>
                    <div id="color-list" class="space-y-4"></div>
                </div>
                <div class="flex justify-between">
                    <button type="button" onclick="goToStep(1)"
                        class="px-5 py-2.5 border border-gray-200 bg-white text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition flex items-center gap-2">
                        <i class="fa-solid fa-arrow-left text-xs"></i> Back
                    </button>
                    <button type="button" onclick="goToStep(3)"
                        class="px-6 py-2.5 bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold rounded-lg transition flex items-center gap-2">
                        Next: Add Variants <i class="fa-solid fa-arrow-right text-xs"></i>
                    </button>
                </div>
            </div>

            <!-- ░░ STEP 3 — Variants ░░ -->
            <div id="step-panel-3" class="step-panel hidden">
                <div id="variants-wrapper" class="space-y-5 mb-4"></div>
                <div class="flex justify-between">
                    <button type="button" onclick="goToStep(2)"
                        class="px-5 py-2.5 border border-gray-200 bg-white text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition flex items-center gap-2">
                        <i class="fa-solid fa-arrow-left text-xs"></i> Back
                    </button>
                    <button type="button" onclick="goToStep(4)"
                        class="px-6 py-2.5 bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold rounded-lg transition flex items-center gap-2">
                        Next: Specifications <i class="fa-solid fa-arrow-right text-xs"></i>
                    </button>
                </div>
            </div>

            <!-- ░░ STEP 4 — Specifications ░░ -->
            <div id="step-panel-4" class="step-panel hidden">
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 mb-4">
                    <div class="flex items-center justify-between mb-5 pb-4 border-b border-gray-100">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center">
                                <i class="fa-solid fa-list-check text-amber-500 text-sm"></i>
                            </div>
                            <div>
                                <h2 class="text-sm font-semibold text-gray-800">Step 4 — Specifications</h2>
                                <p class="text-xs text-gray-400">Add key-value product specs (e.g. Material, Weight).
                                </p>
                            </div>
                        </div>
                        <button type="button" onclick="addSpec()"
                            class="flex items-center gap-2 px-4 py-2 text-xs font-semibold text-amber-600 bg-amber-50 hover:bg-amber-100 rounded-lg transition">
                            <i class="fa-solid fa-plus"></i> Add Spec
                        </button>
                    </div>
                    <div id="spec-list" class="space-y-3"></div>
                    <p id="spec-empty" class="text-xs text-gray-400 text-center py-6">No specifications yet. Click "Add
                        Spec" to begin.</p>
                </div>
                <div class="flex justify-between">
                    <button type="button" onclick="goToStep(3)"
                        class="px-5 py-2.5 border border-gray-200 bg-white text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition flex items-center gap-2">
                        <i class="fa-solid fa-arrow-left text-xs"></i> Back
                    </button>
                    <button type="button" onclick="goToStep(5)"
                        class="px-6 py-2.5 bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold rounded-lg transition flex items-center gap-2">
                        Next: Gallery <i class="fa-solid fa-arrow-right text-xs"></i>
                    </button>
                </div>
            </div>

            <!-- ░░ STEP 5 — Gallery ░░ -->
            <div id="step-panel-5" class="step-panel hidden">
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 mb-4">
                    <div class="flex items-center justify-between mb-5 pb-4 border-b border-gray-100">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center">
                                <i class="fa-solid fa-images text-amber-500 text-sm"></i>
                            </div>
                            <div>
                                <h2 class="text-sm font-semibold text-gray-800">Step 5 — Gallery</h2>
                                <p class="text-xs text-gray-400">Upload additional product photos (saved as .webp).</p>
                            </div>
                        </div>
                        <label
                            class="flex items-center gap-2 px-4 py-2 text-xs font-semibold text-amber-600 bg-amber-50 hover:bg-amber-100 rounded-lg transition cursor-pointer">
                            <i class="fa-solid fa-plus"></i> Add Photos
                            <input type="file" id="gallery-file-input" name="gallery_images[]" accept="image/*" multiple
                                class="hidden" onchange="handleGalleryAdd(this)">
                        </label>
                    </div>
                    <div id="gallery-grid" class="grid grid-cols-4 gap-3"></div>
                    <p id="gallery-empty" class="text-xs text-gray-400 text-center py-6">No gallery photos yet.</p>
                </div>
                <div class="flex justify-between">
                    <button type="button" onclick="goToStep(5)"
                        class="px-5 py-2.5 border border-gray-200 bg-white text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition flex items-center gap-2">
                        <i class="fa-solid fa-arrow-left text-xs"></i> Back
                    </button>
                    <button type="button" onclick="goToStep(6)"
                        class="px-6 py-2.5 bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold rounded-lg transition flex items-center gap-2">
                        Next: Review <i class="fa-solid fa-arrow-right text-xs"></i>
                    </button>
                </div>
            </div>

            <!-- ░░ STEP 6 — Review ░░ -->
            <div id="step-panel-6" class="step-panel hidden">
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 mb-4">
                    <div class="flex items-center gap-3 mb-5 pb-4 border-b border-gray-100">
                        <div class="w-8 h-8 rounded-lg bg-green-50 flex items-center justify-center">
                            <i class="fa-solid fa-clipboard-check text-green-500 text-sm"></i>
                        </div>
                        <div>
                            <h2 class="text-sm font-semibold text-gray-800">Step 6 — Review &amp; Submit</h2>
                            <p class="text-xs text-gray-400">Check your entries before saving.</p>
                        </div>
                    </div>
                    <div id="review-content" class="text-sm text-gray-700 space-y-4"></div>
                </div>
                <div class="flex justify-between">
                    <button type="button" onclick="goToStep(5)"
                        class="px-5 py-2.5 border border-gray-200 bg-white text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition flex items-center gap-2">
                        <i class="fa-solid fa-arrow-left text-xs"></i> Back
                    </button>
                    <button type="submit" name="submit_product"
                        class="px-8 py-2.5 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-lg transition flex items-center gap-2">
                        <i class="fa-solid fa-floppy-disk"></i> Save Product
                    </button>
                </div>
            </div>

        </form>
    </div>


    <!-- ╔══════════════════════════════════════════════════════╗ -->
    <!-- ║         CATEGORY SETTINGS SLIDE-OVER PANEL          ║ -->
    <!-- ╚══════════════════════════════════════════════════════╝ -->

    <!-- Backdrop -->
    <div id="cat-backdrop" onclick="closeCatPanel()"
        class="fixed inset-0 bg-black/30 z-40 hidden transition-opacity duration-200"></div>

    <!-- Panel -->
    <div id="cat-panel" class="fixed top-0 right-0 h-screen w-80 bg-white shadow-xl z-50 flex flex-col
                translate-x-full transition-transform duration-300">

        <!-- Header -->
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 shrink-0">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-gear text-amber-500"></i>
                <span class="text-sm font-semibold text-gray-800">Manage Categories</span>
            </div>
            <button onclick="closeCatPanel()"
                class="w-7 h-7 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center text-gray-500 transition">
                <i class="fa-solid fa-xmark text-xs"></i>
            </button>
        </div>

        <!-- Add new -->
        <div class="px-5 py-4 border-b border-gray-100 shrink-0">
            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest mb-2">Add New Category</p>
            <div class="flex gap-2">
                <input type="text" id="cat-new-input" placeholder="Category name…" maxlength="100"
                    class="flex-1 px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 focus:outline-none focus:border-amber-400 focus:bg-white transition">
                <button onclick="catAdd()"
                    class="px-3 py-2 bg-amber-500 hover:bg-amber-600 text-white text-xs font-semibold rounded-lg transition flex items-center gap-1.5">
                    <i class="fa-solid fa-plus"></i> Add
                </button>
            </div>
            <p id="cat-error" class="text-xs text-red-500 mt-1.5 hidden"></p>
        </div>

        <!-- List -->
        <div class="flex-1 overflow-y-auto px-5 py-3">
            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest mb-3">Existing Categories</p>
            <ul id="cat-list" class="space-y-2">
                <?php foreach ($categories as $cat): ?>
                    <li id="cat-item-<?= $cat['id'] ?>"
                        class="flex items-center gap-2 group px-3 py-2 rounded-lg border border-gray-100 bg-gray-50 hover:border-amber-200 transition">
                        <span class="flex-1 text-sm text-gray-700 cat-display"><?= htmlspecialchars($cat['name']) ?></span>
                        <input type="text" value="<?= htmlspecialchars($cat['name']) ?>"
                            class="cat-edit-input hidden flex-1 text-sm px-2 py-0.5 border border-amber-300 rounded focus:outline-none bg-white"
                            onkeydown="if(event.key==='Enter') catSaveEdit(<?= $cat['id'] ?>, this)">
                        <button type="button" onclick="catStartEdit(<?= $cat['id'] ?>)"
                            class="cat-edit-btn w-6 h-6 rounded flex items-center justify-center text-gray-300 hover:text-amber-500 transition opacity-0 group-hover:opacity-100">
                            <i class="fa-solid fa-pen text-[10px]"></i>
                        </button>
                        <button type="button" onclick="catSaveEdit(<?= $cat['id'] ?>)"
                            class="cat-save-btn hidden w-6 h-6 rounded flex items-center justify-center text-green-500 hover:text-green-600 transition">
                            <i class="fa-solid fa-check text-[10px]"></i>
                        </button>
                        <button type="button" onclick="catCancelEdit(<?= $cat['id'] ?>)"
                            class="cat-cancel-btn hidden w-6 h-6 rounded flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                            <i class="fa-solid fa-xmark text-[10px]"></i>
                        </button>
                        <button type="button" onclick="catDelete(<?= $cat['id'] ?>)"
                            class="cat-del-btn w-6 h-6 rounded flex items-center justify-center text-gray-300 hover:text-red-500 transition opacity-0 group-hover:opacity-100">
                            <i class="fa-solid fa-trash text-[10px]"></i>
                        </button>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>


    <script>
        /* ═══════════════════════════════════════════════════════
           STEP WIZARD
        ═══════════════════════════════════════════════════════ */
        let currentStep = 1;
        let colorCount = 0;
        const variantCount = {};
        const UNITS = ['mm', 'cm', 'm', 'inches'];


        /* ═══════════════════════════════════════════════════════
           COLORS
        ═══════════════════════════════════════════════════════ */
        function addColor() {
            const ci = colorCount++;
            variantCount[ci] = 0;
            const list = document.getElementById('color-list');

            const card = document.createElement('div');
            card.id = 'color-card-' + ci;
            card.className = 'border border-gray-100 rounded-xl p-5 bg-gray-50 relative';
            card.innerHTML = `
            <button type="button" onclick="removeColor(${ci})"
                class="absolute top-3 right-3 w-7 h-7 rounded-full bg-red-50 text-red-400 hover:bg-red-100 flex items-center justify-center transition text-xs">
                <i class="fa-solid fa-xmark"></i>
            </button>
            <p class="text-xs font-bold text-gray-600 uppercase tracking-widest mb-4">Color #${ci + 1}</p>
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1.5 uppercase tracking-wider">Color Name <span class="text-red-400">*</span></label>
                    <input type="text" name="color_name[${ci}]" placeholder="e.g. Walnut Brown" required
                        class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-white focus:outline-none focus:border-amber-400 transition"
                        oninput="syncColorName(${ci}, this.value)">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1.5 uppercase tracking-wider">Base Price (₱)</label>
                    <input type="number" name="color_price[${ci}]" placeholder="0.00" min="0" step="0.01"
                        class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-white focus:outline-none focus:border-amber-400 transition">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1.5 uppercase tracking-wider">
                        Color Image <span class="text-[10px] text-gray-400 normal-case font-normal">(.webp)</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer w-full px-3 py-2 text-sm border border-dashed border-gray-200 rounded-lg bg-white hover:border-amber-400 transition overflow-hidden">
                        <img id="color-img-preview-${ci}" src="" alt="" class="hidden w-8 h-8 rounded object-cover shrink-0">
                        <span id="color-img-text-${ci}" class="text-gray-400 text-xs truncate">Upload image</span>
                        <input type="file" name="color_image[${ci}]" accept="image/*" class="hidden"
                            onchange="previewColorImg(${ci}, this)">
                    </label>
                </div>
            </div>`;
            list.appendChild(card);
        }

        function removeColor(ci) { document.getElementById('color-card-' + ci)?.remove(); }

        function previewColorImg(ci, input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = e => {
                    const img = document.getElementById('color-img-preview-' + ci);
                    const text = document.getElementById('color-img-text-' + ci);
                    img.src = e.target.result;
                    img.classList.remove('hidden');
                    text.textContent = input.files[0].name;
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        function syncColorName(ci, val) {
            const h = document.getElementById('variant-color-header-' + ci);
            if (h) h.textContent = val || ('Color #' + (ci + 1));
        }

        /* ═══════════════════════════════════════════════════════
           VARIANTS
        ═══════════════════════════════════════════════════════ */
        function getActiveColorIndices() {
            return Array.from(document.querySelectorAll('[id^="color-card-"]'))
                .map(c => parseInt(c.id.replace('color-card-', '')));
        }

        function renderVariantsWrapper() {
            const wrapper = document.getElementById('variants-wrapper');
            wrapper.innerHTML = '';
            const indices = getActiveColorIndices();
            if (!indices.length) {
                wrapper.innerHTML = '<p class="text-sm text-gray-400 text-center py-8">No colors added yet. Go back to Step 2.</p>';
                return;
            }
            indices.forEach(ci => {
                const colorName = document.querySelector(`input[name="color_name[${ci}]"]`)?.value || ('Color #' + (ci + 1));
                const section = document.createElement('div');
                section.className = 'bg-white rounded-xl border border-gray-100 shadow-sm p-6';
                section.innerHTML = `
                <div class="flex items-center justify-between mb-4 pb-4 border-b border-gray-100">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center">
                            <i class="fa-solid fa-tag text-amber-500 text-sm"></i>
                        </div>
                        <div>
                            <h2 class="text-sm font-semibold text-gray-800">Variants for:
                                <span id="variant-color-header-${ci}" class="text-amber-600">${colorName}</span>
                            </h2>
                            <p class="text-xs text-gray-400">Add sizes and dimensions.</p>
                        </div>
                    </div>
                    <button type="button" onclick="addVariant(${ci})"
                        class="flex items-center gap-2 px-3 py-1.5 text-xs font-semibold text-amber-600 bg-amber-50 hover:bg-amber-100 rounded-lg transition">
                        <i class="fa-solid fa-plus"></i> Add Size
                    </button>
                </div>
                <div id="variant-list-${ci}" class="space-y-3"></div>`;
                wrapper.appendChild(section);
                if (!variantCount[ci]) variantCount[ci] = 0;
                for (let i = 0; i < variantCount[ci]; i++) _insertVariantRow(ci, i);
                if (variantCount[ci] === 0) addVariant(ci);
            });
        }

        function addVariant(ci) {
            const vi = variantCount[ci] ?? 0;
            variantCount[ci] = vi + 1;
            _insertVariantRow(ci, vi);
        }

        function _insertVariantRow(ci, vi) {
            const list = document.getElementById('variant-list-' + ci);
            if (!list) return;
            const row = document.createElement('div');
            row.id = `variant-row-${ci}-${vi}`;
            row.className = 'grid grid-cols-9 gap-3 items-end border border-gray-100 rounded-lg p-3 bg-gray-50';
            const unitOpts = UNITS.map(u => `<option value="${u}">${u}</option>`).join('');
            row.innerHTML = `
            <div class="col-span-2">
                <label class="block text-[10px] text-gray-500 font-medium uppercase tracking-wider mb-1">Size Name <span class="text-red-400">*</span></label>
                <input type="text" name="size_name[${ci}][${vi}]" placeholder="e.g. 60x120cm" required
                    class="w-full px-2.5 py-2 text-xs border border-gray-200 rounded-lg bg-white focus:outline-none focus:border-amber-400 transition">
            </div>
            <div>
                <label class="block text-[10px] text-gray-500 font-medium uppercase tracking-wider mb-1">Price (₱)</label>
                <input type="number" name="size_price[${ci}][${vi}]" placeholder="0.00" min="0" step="0.01"
                    class="w-full px-2.5 py-2 text-xs border border-gray-200 rounded-lg bg-white focus:outline-none focus:border-amber-400 transition">
            </div>
            <div>
                <label class="block text-[10px] text-gray-500 font-medium uppercase tracking-wider mb-1">Discount (%)</label>
                <input type="number" name="size_discount[${ci}][${vi}]" placeholder="0" min="0" max="100" step="0.01"
                    class="w-full px-2.5 py-2 text-xs border border-gray-200 rounded-lg bg-white focus:outline-none focus:border-amber-400 transition">
            </div>
            <div>
                <label class="block text-[10px] text-gray-500 font-medium uppercase tracking-wider mb-1">Stock</label>
                <input type="number" name="size_stock[${ci}][${vi}]" placeholder="0" min="0" step="1"
                    class="w-full px-2.5 py-2 text-xs border border-gray-200 rounded-lg bg-white focus:outline-none focus:border-amber-400 transition">
            </div>
            <div>
                <label class="block text-[10px] text-gray-500 font-medium uppercase tracking-wider mb-1">Width</label>
                <input type="number" name="size_width[${ci}][${vi}]" placeholder="0" min="0" step="0.01"
                    class="w-full px-2.5 py-2 text-xs border border-gray-200 rounded-lg bg-white focus:outline-none focus:border-amber-400 transition">
            </div>
            <div>
                <label class="block text-[10px] text-gray-500 font-medium uppercase tracking-wider mb-1">Height</label>
                <input type="number" name="size_height[${ci}][${vi}]" placeholder="0" min="0" step="0.01"
                    class="w-full px-2.5 py-2 text-xs border border-gray-200 rounded-lg bg-white focus:outline-none focus:border-amber-400 transition">
            </div>
            <div>
                <label class="block text-[10px] text-gray-500 font-medium uppercase tracking-wider mb-1">Length</label>
                <input type="number" name="size_length[${ci}][${vi}]" placeholder="0" min="0" step="0.01"
                    class="w-full px-2.5 py-2 text-xs border border-gray-200 rounded-lg bg-white focus:outline-none focus:border-amber-400 transition">
            </div>
            <div class="flex items-end gap-1.5">
                <div class="flex-1">
                    <label class="block text-[10px] text-gray-500 font-medium uppercase tracking-wider mb-1">Unit</label>
                    <select name="size_dimension_unit[${ci}][${vi}]"
                        class="w-full px-2 py-2 text-xs border border-gray-200 rounded-lg bg-white focus:outline-none focus:border-amber-400 transition">
                        ${unitOpts}
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-[10px] text-gray-500 font-medium uppercase tracking-wider mb-1">Weight</label>
                <input type="number" name="size_weight[${ci}][${vi}]" placeholder="0" min="0" step="0.01"
                    class="w-full px-2.5 py-2 text-xs border border-gray-200 rounded-lg bg-white focus:outline-none focus:border-amber-400 transition">
            </div>
            <div class="flex items-end gap-1.5">
                <div class="flex-1">
                    <label class="block text-[10px] text-gray-500 font-medium uppercase tracking-wider mb-1">W. Unit</label>
                    <select name="size_weight_unit[${ci}][${vi}]"
                        class="w-full px-2 py-2 text-xs border border-gray-200 rounded-lg bg-white focus:outline-none focus:border-amber-400 transition">
                        <option value="kg">kg</option>
                        <option value="g">g</option>
                        <option value="lbs">lbs</option>
                        <option value="oz">oz</option>
                    </select>
                </div>
                <button type="button" onclick="removeVariant(${ci}, ${vi})"
                    class="w-7 h-7 shrink-0 rounded-lg bg-red-50 text-red-400 hover:bg-red-100 flex items-center justify-center transition text-xs mb-0.5">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>`;
            list.appendChild(row);
        }

        function removeVariant(ci, vi) { document.getElementById(`variant-row-${ci}-${vi}`)?.remove(); }

        /* ═══════════════════════════════════════════════════════
           REVIEW
        ═══════════════════════════════════════════════════════ */
        function renderReview() {
            const el = document.getElementById('review-content');
            const pName = document.querySelector('[name="product_name"]')?.value || '—';
            const pCat = document.querySelector('[name="product_category"]')?.value || '—';
            const pDesc = document.querySelector('[name="product_description"]')?.value || '—';
            const pImg = document.getElementById('product-img-preview');

            let html = `
            <div class="rounded-lg border border-gray-100 p-4 bg-gray-50">
                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3">Product Info</p>
                <div class="flex gap-4 items-start">
                    ${pImg && pImg.src && !pImg.classList.contains('hidden')
                    ? `<img src="${pImg.src}" class="w-20 h-20 rounded-lg object-cover shrink-0 border border-gray-200">`
                    : '<div class="w-20 h-20 rounded-lg bg-gray-200 flex items-center justify-center text-gray-400 shrink-0"><i class="fa-solid fa-image text-2xl"></i></div>'}
                    <div class="space-y-1">
                        <p><span class="text-gray-400 text-xs">Name:</span> <strong>${pName}</strong></p>
                        <p><span class="text-gray-400 text-xs">Category:</span> ${pCat}</p>
                        <p class="text-xs text-gray-500">${pDesc || '<em>No description</em>'}</p>
                        <p><span class="text-gray-400 text-xs">Unit:</span> ${document.querySelector('[name="product_unit"]')?.value || '—'}</p>
                    </div>
                </div>
            </div>`;

            getActiveColorIndices().forEach(ci => {
                const cName = document.querySelector(`input[name="color_name[${ci}]"]`)?.value || '—';
                const cPrice = document.querySelector(`input[name="color_price[${ci}]"]`)?.value || '0';
                const cImg = document.getElementById('color-img-preview-' + ci);
                html += `
                <div class="rounded-lg border border-gray-100 p-4">
                    <div class="flex items-center gap-3 mb-3">
                        ${cImg && cImg.src && !cImg.classList.contains('hidden')
                        ? `<img src="${cImg.src}" class="w-10 h-10 rounded-lg object-cover shrink-0 border border-gray-200">`
                        : '<div class="w-10 h-10 rounded-lg bg-amber-100 flex items-center justify-center text-amber-500 shrink-0"><i class="fa-solid fa-palette text-sm"></i></div>'}
                        <div>
                            <p class="font-semibold text-gray-800">${cName}</p>
                            <p class="text-xs text-gray-400">Base price: ₱${parseFloat(cPrice || 0).toFixed(2)}</p>
                        </div>
                    </div>
                    <table class="w-full text-xs text-gray-700 border-collapse">
                        <thead>
                            <tr class="bg-gray-50 text-gray-400 uppercase tracking-wider">
                                <th class="text-left px-3 py-2 border-b border-gray-100">Size</th>
                                <th class="text-right px-3 py-2 border-b border-gray-100">Price</th>
                                <th class="text-right px-3 py-2 border-b border-gray-100">Discount</th>
                                <th class="text-right px-3 py-2 border-b border-gray-100">Stock</th>
                                <th class="text-right px-3 py-2 border-b border-gray-100">W × H × L</th>
                               <th class="text-center px-3 py-2 border-b border-gray-100">Unit</th>
                                <th class="text-right px-3 py-2 border-b border-gray-100">Weight</th>
                                <th class="text-center px-3 py-2 border-b border-gray-100">W. Unit</th>
                            </tr>
                        </thead>
                        <tbody id="review-variants-${ci}"></tbody>
                    </table>
                </div>`;
            });

            el.innerHTML = html;

            getActiveColorIndices().forEach(ci => {
                const tbody = document.getElementById('review-variants-' + ci);
                if (!tbody) return;
                const rows = document.querySelectorAll(`[id^="variant-row-${ci}-"]`);
                rows.forEach(row => {
                    const get = sel => row.querySelector(sel)?.value || '0';
                    tbody.insertAdjacentHTML('beforeend', `
                    <tr class="border-b border-gray-50 hover:bg-gray-50 transition">
                        <td class="px-3 py-2 font-medium">${row.querySelector('[name^="size_name"]')?.value || '—'}</td>
                        <td class="px-3 py-2 text-right">₱${parseFloat(get('[name^="size_price"]')).toFixed(2)}</td>
                        <td class="px-3 py-2 text-right">${parseFloat(get('[name^="size_discount"]')).toFixed(2)}%</td>
                        <td class="px-3 py-2 text-right">${parseInt(get('[name^="size_stock"]'), 10)}</td>
                        <td class="px-3 py-2 text-right">${get('[name^="size_width"]')} × ${get('[name^="size_height"]')} × ${get('[name^="size_length"]')}</td>
                        <td class="px-3 py-2 text-center">${row.querySelector('[name^="size_dimension_unit"]')?.value || 'cm'}</td>
                        <td class="px-3 py-2 text-right">${parseFloat(get('[name^="size_weight"]')).toFixed(2)}</td>
                        <td class="px-3 py-2 text-center">${row.querySelector('[name^="size_weight_unit"]')?.value || 'kg'}</td>
                    </tr>`);
                });
                if (!rows.length) {
                    tbody.innerHTML = '<tr><td colspan="8" class="px-3 py-3 text-center text-gray-400 italic">No variants added.</td></tr>';
                }
            });
        }

        /* ═══════════════════════════════════════════════════════
           CATEGORY PANEL
        ═══════════════════════════════════════════════════════ */
        const SELF = window.location.pathname + window.location.search;

        function openCatPanel() {
            document.getElementById('cat-panel').classList.remove('translate-x-full');
            document.getElementById('cat-backdrop').classList.remove('hidden');
        }
        function closeCatPanel() {
            document.getElementById('cat-panel').classList.add('translate-x-full');
            document.getElementById('cat-backdrop').classList.add('hidden');
        }

        function showCatError(msg) {
            const el = document.getElementById('cat-error');
            el.textContent = msg;
            el.classList.remove('hidden');
            setTimeout(() => el.classList.add('hidden'), 3000);
        }

        async function catAdd() {
            const input = document.getElementById('cat-new-input');
            const name = input.value.trim();
            if (!name) return showCatError('Please enter a category name.');

            const fd = new FormData();
            fd.append('category_action', 'add');
            fd.append('cat_name', name);

            const res = await fetch(SELF, { method: 'POST', body: fd });
            const data = await res.json();

            if (!data.ok) return showCatError(data.msg);

            const sel = document.getElementById('category-select');
            const opt = new Option(data.name, data.name);
            sel.appendChild(opt);
            sel.value = data.name;

            const li = document.createElement('li');
            li.id = 'cat-item-' + data.id;
            li.className = 'flex items-center gap-2 group px-3 py-2 rounded-lg border border-gray-100 bg-gray-50 hover:border-amber-200 transition';
            li.innerHTML = `
            <span class="flex-1 text-sm text-gray-700 cat-display">${escHtml(data.name)}</span>
            <input type="text" value="${escHtml(data.name)}"
                class="cat-edit-input hidden flex-1 text-sm px-2 py-0.5 border border-amber-300 rounded focus:outline-none bg-white"
                onkeydown="if(event.key==='Enter') catSaveEdit(${data.id}, this)">
            <button type="button" onclick="catStartEdit(${data.id})"
                class="cat-edit-btn w-6 h-6 rounded flex items-center justify-center text-gray-300 hover:text-amber-500 transition opacity-0 group-hover:opacity-100">
                <i class="fa-solid fa-pen text-[10px]"></i>
            </button>
            <button type="button" onclick="catSaveEdit(${data.id})"
                class="cat-save-btn hidden w-6 h-6 rounded flex items-center justify-center text-green-500 hover:text-green-600 transition">
                <i class="fa-solid fa-check text-[10px]"></i>
            </button>
            <button type="button" onclick="catCancelEdit(${data.id})"
                class="cat-cancel-btn hidden w-6 h-6 rounded flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                <i class="fa-solid fa-xmark text-[10px]"></i>
            </button>
            <button type="button" onclick="catDelete(${data.id})"
                class="cat-del-btn w-6 h-6 rounded flex items-center justify-center text-gray-300 hover:text-red-500 transition opacity-0 group-hover:opacity-100">
                <i class="fa-solid fa-trash text-[10px]"></i>
            </button>`;
            document.getElementById('cat-list').appendChild(li);
            input.value = '';
        }

        function catStartEdit(id) {
            const li = document.getElementById('cat-item-' + id);
            li.querySelector('.cat-display').classList.add('hidden');
            li.querySelector('.cat-edit-input').classList.remove('hidden');
            li.querySelector('.cat-edit-btn').classList.add('hidden');
            li.querySelector('.cat-del-btn').classList.add('hidden');
            li.querySelector('.cat-save-btn').classList.remove('hidden');
            li.querySelector('.cat-cancel-btn').classList.remove('hidden');
            li.querySelector('.cat-edit-input').focus();
        }

        function catCancelEdit(id) {
            const li = document.getElementById('cat-item-' + id);
            li.querySelector('.cat-edit-input').value = li.querySelector('.cat-display').textContent;
            li.querySelector('.cat-display').classList.remove('hidden');
            li.querySelector('.cat-edit-input').classList.add('hidden');
            li.querySelector('.cat-edit-btn').classList.remove('hidden');
            li.querySelector('.cat-del-btn').classList.remove('hidden');
            li.querySelector('.cat-save-btn').classList.add('hidden');
            li.querySelector('.cat-cancel-btn').classList.add('hidden');
        }

        async function catSaveEdit(id, inputEl) {
            const li = document.getElementById('cat-item-' + id);
            const inp = inputEl || li.querySelector('.cat-edit-input');
            const name = inp.value.trim();
            if (!name) return;

            const fd = new FormData();
            fd.append('category_action', 'update');
            fd.append('cat_id', id);
            fd.append('cat_name', name);

            const res = await fetch(SELF, { method: 'POST', body: fd });
            const data = await res.json();
            if (!data.ok) return showCatError(data.msg || 'Update failed.');

            li.querySelector('.cat-display').textContent = data.name;

            const sel = document.getElementById('category-select');
            Array.from(sel.options).forEach(opt => {
                if (opt.value === li.querySelector('.cat-display').textContent ||
                    opt.textContent.trim() === li.querySelector('.cat-edit-input').defaultValue) {
                    opt.value = data.name;
                    opt.textContent = data.name;
                }
            });

            catCancelEdit(id);
        }

        async function catDelete(id) {
            if (!confirm('Delete this category? Products using it will not be affected.')) return;

            const fd = new FormData();
            fd.append('category_action', 'delete');
            fd.append('cat_id', id);

            const res = await fetch(SELF, { method: 'POST', body: fd });
            const data = await res.json();
            if (!data.ok) return showCatError(data.msg || 'Delete failed.');

            document.getElementById('cat-item-' + id)?.remove();

            const sel = document.getElementById('category-select');
            Array.from(sel.options).forEach(opt => {
                if (opt.dataset.catId == id) opt.remove();
            });
        }

        function escHtml(str) {
            return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        /* ═══════════════════════════════════════════════════════
           INIT
        ═══════════════════════════════════════════════════════ */

        /* ═══════════════════════════════════════════════════════
          SPECIFICATIONS
       ═══════════════════════════════════════════════════════ */
        let specCount = 0;

        function addSpec() {
            const si = specCount++;
            const list = document.getElementById('spec-list');
            document.getElementById('spec-empty').classList.add('hidden');

            const row = document.createElement('div');
            row.id = 'spec-row-' + si;
            row.className = 'flex items-center gap-3';
            row.innerHTML = `
            <input type="text" name="spec_key[]" placeholder="e.g. Material" maxlength="100"
                class="w-40 px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 focus:outline-none focus:border-amber-400 focus:bg-white transition shrink-0">
            <span class="text-gray-300 text-sm">:</span>
            <input type="text" name="spec_value[]" placeholder="e.g. Solid Oak" maxlength="255"
                class="flex-1 px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 focus:outline-none focus:border-amber-400 focus:bg-white transition">
            <button type="button" onclick="removeSpec(${si})"
                class="w-7 h-7 shrink-0 rounded-lg bg-red-50 text-red-400 hover:bg-red-100 flex items-center justify-center transition text-xs">
                <i class="fa-solid fa-xmark"></i>
            </button>`;
            list.appendChild(row);
        }

        function removeSpec(si) {
            document.getElementById('spec-row-' + si)?.remove();
            if (!document.querySelectorAll('[id^="spec-row-"]').length) {
                document.getElementById('spec-empty').classList.remove('hidden');
            }
        }

        /* ═══════════════════════════════════════════════════════
           GALLERY
        ═══════════════════════════════════════════════════════ */
        // We keep a DataTransfer to accumulate files across multiple "Add Photos" clicks
        const galleryDT = new DataTransfer();

        function handleGalleryAdd(input) {
            Array.from(input.files).forEach(file => {
                // avoid exact duplicate filenames
                const exists = Array.from(galleryDT.files).some(f => f.name === file.name && f.size === file.size);
                if (!exists) galleryDT.items.add(file);
            });
            // push accumulated list back to the real input
            input.files = galleryDT.files;
            renderGalleryGrid();
            // reset so onChange fires again if user picks same file after removal
            input.value = '';
        }

        function renderGalleryGrid() {
            const grid = document.getElementById('gallery-grid');
            const empty = document.getElementById('gallery-empty');
            const files = Array.from(galleryDT.files);
            grid.innerHTML = '';

            if (!files.length) { empty.classList.remove('hidden'); return; }
            empty.classList.add('hidden');

            files.forEach((file, idx) => {
                const reader = new FileReader();
                reader.onload = e => {
                    const card = document.createElement('div');
                    card.className = 'relative group rounded-lg overflow-hidden border border-gray-100 bg-gray-50 aspect-square';
                    card.innerHTML = `
                    <img src="${e.target.result}" class="w-full h-full object-cover">
                    <button type="button" onclick="removeGalleryItem(${idx})"
                        class="absolute top-1.5 right-1.5 w-6 h-6 rounded-full bg-black/50 text-white flex items-center justify-center text-[10px]
                               opacity-0 group-hover:opacity-100 transition hover:bg-red-500">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                    <p class="absolute bottom-0 left-0 right-0 bg-black/40 text-white text-[9px] px-2 py-1 truncate">${file.name}</p>`;
                    grid.appendChild(card);
                };
                reader.readAsDataURL(file);
            });
        }

        function removeGalleryItem(idx) {
            const newDT = new DataTransfer();
            Array.from(galleryDT.files).forEach((f, i) => { if (i !== idx) newDT.items.add(f); });
            // clear and refill galleryDT
            while (galleryDT.items.length) galleryDT.items.remove(0);
            Array.from(newDT.files).forEach(f => galleryDT.items.add(f));
            // sync real input
            document.getElementById('gallery-file-input').files = galleryDT.files;
            renderGalleryGrid();
        }

        function goToStep(n) {
            document.querySelectorAll('.step-panel').forEach(p => p.classList.add('hidden'));
            document.getElementById('step-panel-' + n).classList.remove('hidden');

            for (let i = 1; i <= 6; i++) {
                const circle = document.getElementById('step-circle-' + i);
                const label = document.getElementById('step-label-' + i);
                const line = document.getElementById('step-line-' + i);

                if (i < n) {
                    circle.className = 'w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold bg-amber-100 text-amber-600 transition-all duration-200';
                    circle.innerHTML = '<i class="fa-solid fa-check text-xs"></i>';
                    label.className = 'text-xs font-medium text-amber-500';
                } else if (i === n) {
                    circle.className = 'w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold bg-amber-500 text-white transition-all duration-200';
                    circle.innerHTML = i;
                    label.className = 'text-xs font-medium text-amber-600';
                } else {
                    circle.className = 'w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold bg-white border-2 border-gray-200 text-gray-400 transition-all duration-200';
                    circle.innerHTML = i;
                    label.className = 'text-xs font-medium text-gray-400';
                }
                if (line) {
                    const bg = i < n ? 'bg-amber-300' : i === n ? 'bg-amber-200' : 'bg-gray-200';
                    line.className = line.className.replace(/bg-\S+/, bg);
                }
            }

            currentStep = n;
            if (n === 3) renderVariantsWrapper();
            if (n === 6) renderReview();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        /* ═══════════════════════════════════════════════════════
           PRODUCT IMAGE PREVIEW
        ═══════════════════════════════════════════════════════ */
        document.getElementById('product-img-input').addEventListener('change', function () {
            const preview = document.getElementById('product-img-preview');
            const holder = document.getElementById('product-img-placeholder');
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = e => {
                    preview.src = e.target.result;
                    preview.classList.remove('hidden');
                    holder.classList.add('hidden');
                };
                reader.readAsDataURL(this.files[0]);
            }
        });

        addColor();
    </script>

</body>

</html>