import React, { useEffect, useRef } from 'react';
import JsBarcode from 'jsbarcode';

const Barcode = ({
    value,
    format,
    width,
    height,
    displayValue = true,
    text,
    fontOptions,
    font,
    textAlign,
    textPosition,
    textMargin,
    fontSize,
    background,
    lineColor,
    margin,
    marginTop,
    marginBottom,
    marginLeft,
    marginRight,
    flat,
    ean128,
    elementTag = 'svg'
}) => {
    const barcodeRef = useRef(null);

    useEffect(() => {
        const settings = {
            format,
            width,
            height,
            displayValue,
            text,
            fontOptions,
            font,
            textAlign,
            textPosition,
            textMargin,
            fontSize,
            background,
            lineColor,
            margin,
            marginTop,
            marginBottom,
            marginLeft,
            marginRight,
            flat,
            ean128,
            valid: function (valid) {
                // Handle valid state if needed
            },
        };

        removeUndefinedProps(settings);

        JsBarcode(barcodeRef.current, value, settings);
    }, [value, format, width, height, displayValue, text, fontOptions, font, textAlign, textPosition, textMargin, fontSize, background, lineColor, margin, marginTop, marginBottom, marginLeft, marginRight, flat, ean128]);

    return React.createElement(elementTag, { ref: barcodeRef, id: "barcodegen" });
};

// Helper function to remove undefined properties from an object
function removeUndefinedProps(obj) {
    Object.keys(obj).forEach(key => obj[key] === undefined ? delete obj[key] : {});
}

export default Barcode;
