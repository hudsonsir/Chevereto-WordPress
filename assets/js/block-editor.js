// assets/js/block-editor.js
(function(blocks, element, editor, components, i18n) {
    const { registerBlockType } = blocks;
    const { createElement: el, useState } = element;
    const { InspectorControls, BlockControls, AlignmentToolbar } = editor;
    const { 
        PanelBody, 
        TextControl, 
        TextareaControl, 
        RangeControl, 
        Button, 
        DropZone, 
        FormFileUpload,
        Spinner,
        Notice,
        SelectControl
    } = components;
    const { __ } = i18n;

    // 注册区块
    registerBlockType('chevereto-block/image-uploader', {
        title: __('Chevereto图片上传', 'chevereto-block'),
        icon: 'format-image',
        category: 'media',
        description: __('上传图片到Chevereto图床并显示', 'chevereto-block'),
        keywords: [
            __('图片', 'chevereto-block'),
            __('上传', 'chevereto-block'), 
            __('图床', 'chevereto-block'),
            'chevereto'
        ],
        supports: {
            align: ['left', 'center', 'right', 'wide', 'full'],
            html: false
        },
        attributes: {
            imageUrl: {
                type: 'string',
                default: ''
            },
            imageId: {
                type: 'string', 
                default: ''
            },
            title: {
                type: 'string',
                default: ''
            },
            description: {
                type: 'string',
                default: ''
            },
            alignment: {
                type: 'string',
                default: 'center'
            },
            width: {
                type: 'number',
                default: 500
            }
        },

        edit: function(props) {
            const { attributes, setAttributes, className } = props;
            const { imageUrl, imageId, title, description, alignment, width } = attributes;
            
            const [isUploading, setIsUploading] = useState(false);
            const [uploadError, setUploadError] = useState('');
            const [uploadSuccess, setUploadSuccess] = useState('');

            // 检查是否配置了API密钥
            if (!cheveretoBlock.hasApiKey) {
                return el('div', { className: 'chevereto-block-error' },
                    el('p', {}, __('请先在设置页面配置Chevereto API密钥', 'chevereto-block')),
                    el('a', { 
                        href: '/wp-admin/options-general.php?page=chevereto-settings',
                        target: '_blank'
                    }, __('前往设置', 'chevereto-block'))
                );
            }

            // 上传文件函数
            const uploadFile = async (file) => {
                setIsUploading(true);
                setUploadError('');
                setUploadSuccess('');

                try {
                    const formData = new FormData();
                    formData.append('file', file);
                    formData.append('action', 'chevereto_upload');
                    formData.append('nonce', cheveretoBlock.nonce);

                    const response = await fetch(cheveretoBlock.ajaxUrl, {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();

                    if (result.success && result.data.image) {
                        setAttributes({
                            imageUrl: result.data.image.url,
                            imageId: result.data.image.id_encoded,
                            title: result.data.image.title || file.name
                        });
                        setUploadSuccess(__('图片上传成功！', 'chevereto-block'));
                    } else {
                        setUploadError(result.data || __('上传失败', 'chevereto-block'));
                    }
                } catch (error) {
                    setUploadError(__('网络错误：', 'chevereto-block') + error.message);
                } finally {
                    setIsUploading(false);
                }
            };

            // 处理文件选择
            const onSelectFile = (files) => {
                if (files && files.length > 0) {
                    const file = files[0];
                    
                    // 检查文件类型
                    if (!file.type.startsWith('image/')) {
                        setUploadError(__('请选择图片文件', 'chevereto-block'));
                        return;
                    }
                    
                    // 检查文件大小 (10MB)
                    if (file.size > 10 * 1024 * 1024) {
                        setUploadError(__('文件大小不能超过10MB', 'chevereto-block'));
                        return;
                    }
                    
                    uploadFile(file);
                }
            };

            // 删除图片
            const removeImage = () => {
                setAttributes({
                    imageUrl: '',
                    imageId: '',
                    title: '',
                    description: ''
                });
                setUploadSuccess('');
                setUploadError('');
            };

            // 渲染上传区域
            const renderUploadArea = () => {
                return el('div', { className: 'chevereto-block-upload-area' },
                    el(DropZone, {
                        onFilesDrop: onSelectFile,
                        label: __('拖拽图片到这里或点击上传', 'chevereto-block')
                    }),
                    el('div', { className: 'chevereto-block-upload-content' },
                        el('div', { className: 'chevereto-block-upload-icon' }, '📷'),
                        el('p', {}, __('拖拽图片到这里', 'chevereto-block')),
                        el('p', { className: 'chevereto-block-upload-or' }, __('或者', 'chevereto-block')),
                        el(FormFileUpload, {
                            accept: 'image/*',
                            onChange: (event) => onSelectFile(event.target.files),
                            render: ({ openFileDialog }) => (
                                el(Button, {
                                    isPrimary: true,
                                    onClick: openFileDialog,
                                    disabled: isUploading
                                }, isUploading ? __('上传中...', 'chevereto-block') : __('选择图片', 'chevereto-block'))
                            )
                        })
                    ),
                    isUploading && el('div', { className: 'chevereto-block-uploading' },
                        el(Spinner, {}),
                        el('p', {}, __('正在上传图片...', 'chevereto-block'))
                    )
                );
            };

            // 渲染图片预览
            const renderImagePreview = () => {
                return el('div', { 
                    className: 'chevereto-block-preview',
                    style: { textAlign: alignment }
                },
                    el('figure', { 
                        className: 'chevereto-block-figure',
                        style: { maxWidth: width + 'px', margin: '0 auto' }
                    },
                        el('img', {
                            src: imageUrl,
                            alt: title,
                            className: 'chevereto-block-image',
                            style: { maxWidth: '100%', height: 'auto' }
                        }),
                        title && el('figcaption', { 
                            className: 'chevereto-block-title' 
                        }, title),
                        description && el('div', { 
                            className: 'chevereto-block-description' 
                        }, description)
                    ),
                    el('div', { className: 'chevereto-block-actions' },
                        el(Button, {
                            isDestructive: true,
                            onClick: removeImage
                        }, __('删除图片', 'chevereto-block')),
                        el(FormFileUpload, {
                            accept: 'image/*',
                            onChange: (event) => onSelectFile(event.target.files),
                            render: ({ openFileDialog }) => (
                                el(Button, {
                                    onClick: openFileDialog,
                                    disabled: isUploading
                                }, __('更换图片', 'chevereto-block'))
                            )
                        })
                    )
                );
            };

            return el('div', { className: className },
                // 区块控制栏
                el(BlockControls, {},
                    el(AlignmentToolbar, {
                        value: alignment,
                        onChange: (value) => setAttributes({ alignment: value })
                    })
                ),

                // 侧边栏设置
                el(InspectorControls, {},
                    el(PanelBody, { 
                        title: __('图片设置', 'chevereto-block'),
                        initialOpen: true 
                    },
                        el(TextControl, {
                            label: __('标题', 'chevereto-block'),
                            value: title,
                            onChange: (value) => setAttributes({ title: value }),
                            placeholder: __('输入图片标题', 'chevereto-block')
                        }),
                        el(TextareaControl, {
                            label: __('描述', 'chevereto-block'),
                            value: description,
                            onChange: (value) => setAttributes({ description: value }),
                            placeholder: __('输入图片描述', 'chevereto-block'),
                            rows: 3
                        }),
                        el(RangeControl, {
                            label: __('宽度 (像素)', 'chevereto-block'),
                            value: width,
                            onChange: (value) => setAttributes({ width: value }),
                            min: 100,
                            max: 1200,
                            step: 10
                        }),
                        el(SelectControl, {
                            label: __('对齐方式', 'chevereto-block'),
                            value: alignment,
                            onChange: (value) => setAttributes({ alignment: value }),
                            options: [
                                { label: __('左对齐', 'chevereto-block'), value: 'left' },
                                { label: __('居中', 'chevereto-block'), value: 'center' },
                                { label: __('右对齐', 'chevereto-block'), value: 'right' }
                            ]
                        })
                    ),
                    imageUrl && el(PanelBody, {
                        title: __('图片信息', 'chevereto-block'),
                        initialOpen: false
                    },
                        el('p', {},
                            el('strong', {}, __('图片ID：', 'chevereto-block')),
                            imageId
                        ),
                        el('p', {},
                            el('strong', {}, __('图片URL：', 'chevereto-block'))
                        ),
                        el('input', {
                            type: 'text',
                            value: imageUrl,
                            readOnly: true,
                            style: { width: '100%', fontSize: '12px' },
                            onClick: (e) => e.target.select()
                        })
                    )
                ),

                // 错误提示
                uploadError && el(Notice, {
                    status: 'error',
                    isDismissible: true,
                    onRemove: () => setUploadError('')
                }, uploadError),

                // 成功提示
                uploadSuccess && el(Notice, {
                    status: 'success',
                    isDismissible: true,
                    onRemove: () => setUploadSuccess('')
                }, uploadSuccess),

                // 主要内容区域
                imageUrl ? renderImagePreview() : renderUploadArea()
            );
        },

        save: function(props) {
            const { attributes } = props;
            const { imageUrl, title, description, alignment, width } = attributes;

            if (!imageUrl) {
                return null;
            }

            return el('div', { 
                className: 'chevereto-block-wrapper align' + alignment 
            },
                el('figure', { 
                    className: 'chevereto-block-figure',
                    style: { maxWidth: width + 'px' }
                },
                    el('img', {
                        src: imageUrl,
                        alt: title,
                        className: 'chevereto-block-image'
                    }),
                    title && el('figcaption', { 
                        className: 'chevereto-block-title' 
                    }, title),
                    description && el('div', { 
                        className: 'chevereto-block-description' 
                    }, description)
                )
            );
        }
    });

})(
    window.wp.blocks,
    window.wp.element,
    window.wp.blockEditor,
    window.wp.components,
    window.wp.i18n
);