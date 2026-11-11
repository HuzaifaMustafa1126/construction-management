/* eslint-disable react/prop-types */
import {
  forwardRef,
  useContext,
  useEffect,
  useId,
  useLayoutEffect,
  useMemo,
  useRef,
  useState,
} from "react";
import { createPortal } from "react-dom";
import { AppIcon } from "./AppIcon";
import { ModalScope, topModal } from "./modalScope";

// Controlled selection: search text never becomes a form value. Use Controller for React Hook Form.
export const SearchableSelect = forwardRef(function SearchableSelect(
  {
    id,
    name,
    label,
    value,
    onChange,
    onBlur,
    options = [],
    selectedOption,
    placeholder = "Search options…",
    description,
    error,
    disabled = false,
    required = false,
    loading = false,
    requestError,
    onSearchChange,
    filterOptions = true,
    menuMessage,
    searchPlaceholder = "Type to search\u2026",
    maxLength,
  },
  forwardedRef,
) {
  const modalRef = useContext(ModalScope);
  const generatedId = useId();
  const inputId = id || generatedId;
  const listId = `${inputId}-list`;
  const inputRef = useRef(null);
  const menuRef = useRef(null);
  const labels = useRef(new Map());
  const [open, setOpen] = useState(false);
  const [search, setSearch] = useState("");
  const [active, setActive] = useState(-1);
  const [position, setPosition] = useState(null);
  const selected =
    options.find((option) => option.value === value) ||
    (selectedOption?.value === value ? selectedOption : null) ||
    labels.current.get(value);
  useEffect(() => {
    for (const option of options) labels.current.set(option.value, option);
    if (selectedOption)
      labels.current.set(selectedOption.value, selectedOption);
  }, [options, selectedOption]);
  const matches = useMemo(() => {
    if (!filterOptions) return options;
    const term = search.trim().toLocaleLowerCase();
    return options.filter((option) =>
      option.label.toLocaleLowerCase().includes(term),
    );
  }, [options, search, filterOptions]);
  const available = loading || requestError ? [] : matches;
  const activeOption = available[active];
  useEffect(() => {
    onSearchChange?.(search);
  }, [search, onSearchChange]);
  useEffect(() => {
    setActive(-1);
  }, [options, loading, requestError]);
  const close = () => {
    setOpen(false);
    setSearch("");
    setActive(-1);
  };
  const mayOpen = () => !topModal() || topModal() === modalRef?.current;
  useEffect(() => {
    if (disabled) {
      setOpen(false);
      setSearch("");
      setActive(-1);
    }
  }, [disabled]);
  useLayoutEffect(() => {
    const dismiss = () => {
      if (topModal() && topModal() !== modalRef?.current) {
        setOpen(false);
        setSearch("");
        setActive(-1);
      }
    };
    document.addEventListener("app-modal-stack-change", dismiss);
    return () =>
      document.removeEventListener("app-modal-stack-change", dismiss);
  }, [modalRef]);
  const choose = (option) => {
    if (option.disabled || (required && option.value === "")) return;
    labels.current.set(option.value, option);
    onChange(option.value);
    close();
  };
  useLayoutEffect(() => {
    if (!open || disabled) return undefined;
    const reposition = () => {
      const rect = inputRef.current.getBoundingClientRect();
      const viewport = window.visualViewport;
      const top = viewport?.offsetTop || 0;
      const left = viewport?.offsetLeft || 0;
      const height = viewport?.height || window.innerHeight;
      const width = viewport?.width || window.innerWidth;
      const below = top + height - rect.bottom - 8;
      const above = rect.top - top - 8;
      const upward = below < 200 && above > below;
      const rootStyle = getComputedStyle(document.documentElement);
      const limit =
        parseFloat(rootStyle.getPropertyValue("--select-max-height")) *
        parseFloat(rootStyle.fontSize);
      const maxHeight = Math.max(
        0,
        Math.min(limit || 256, upward ? above : below),
      );
      setPosition({
        left: Math.max(
          left + 8,
          Math.min(rect.left, left + width - rect.width - 8),
        ),
        width: Math.min(rect.width, width - 16),
        top: upward ? undefined : rect.bottom + 4,
        bottom: upward ? window.innerHeight - rect.top + 4 : undefined,
        maxHeight,
      });
    };
    reposition();
    window.addEventListener("resize", reposition);
    window.addEventListener("scroll", reposition, true);
    window.visualViewport?.addEventListener("resize", reposition);
    window.visualViewport?.addEventListener("scroll", reposition);
    return () => {
      window.removeEventListener("resize", reposition);
      window.removeEventListener("scroll", reposition, true);
      window.visualViewport?.removeEventListener("resize", reposition);
      window.visualViewport?.removeEventListener("scroll", reposition);
    };
  }, [open, disabled]);
  useEffect(() => {
    if (!open) return undefined;
    const outside = (event) => {
      if (
        !inputRef.current?.contains(event.target) &&
        !menuRef.current?.contains(event.target)
      ) {
        setOpen(false);
        setSearch("");
        setActive(-1);
      }
    };
    document.addEventListener("pointerdown", outside);
    return () => document.removeEventListener("pointerdown", outside);
  }, [open]);
  useEffect(() => {
    if (!open || active < 0) return;
    const menu = menuRef.current;
    const option = menu?.querySelector(`[data-index="${active}"]`);
    if (!option) return;
    const bounds = menu.getBoundingClientRect();
    const row = option.getBoundingClientRect();
    if (row.top < bounds.top) menu.scrollTop -= bounds.top - row.top;
    else if (row.bottom > bounds.bottom)
      menu.scrollTop += row.bottom - bounds.bottom;
  }, [open, active]);
  const keyDown = (event) => {
    if (event.nativeEvent.isComposing) return;
    if (event.key === "Escape" && open) {
      event.preventDefault();
      event.stopPropagation();
      close();
    } else if (event.key === "Tab") {
      close();
    } else if (event.key === "ArrowDown" || event.key === "ArrowUp") {
      event.preventDefault();
      if (!mayOpen()) return;
      setOpen(true);
      const direction = event.key === "ArrowDown" ? 1 : -1;
      let next = active < 0 && direction < 0 ? 0 : active;
      for (let count = 0; count < available.length; count += 1) {
        next = (next + direction + available.length) % available.length;
        if (
          !available[next].disabled &&
          !(required && available[next].value === "")
        ) {
          setActive(next);
          break;
        }
      }
    } else if (event.key === "Enter" && open) {
      event.preventDefault();
      if (activeOption) choose(activeOption);
    }
  };
  const expanded = open && !disabled;
  return (
    <div className="app-field">
      <label htmlFor={inputId}>
        {label}
        {required && " *"}
      </label>
      <div className="search-select-control">
        <input
          id={inputId}
          ref={(element) => {
            inputRef.current = element;
            if (typeof forwardedRef === "function") forwardedRef(element);
            else if (forwardedRef) forwardedRef.current = element;
          }}
          role="combobox"
          type="text"
          autoComplete="off"
          spellCheck={false}
          maxLength={maxLength}
          disabled={disabled}
          aria-required={required}
          aria-invalid={!!error}
          aria-expanded={expanded}
          aria-controls={expanded ? listId : undefined}
          aria-autocomplete="list"
          aria-activedescendant={
            expanded && activeOption ? `${listId}-${active}` : undefined
          }
          aria-describedby={
            [
              description && `${inputId}-description`,
              error && `${inputId}-error`,
            ]
              .filter(Boolean)
              .join(" ") || undefined
          }
          value={expanded ? search : selected?.label || ""}
          placeholder={expanded ? searchPlaceholder : placeholder}
          onFocus={() => {
            if (mayOpen()) {
              setOpen(true);
              setSearch("");
              setActive(-1);
            }
          }}
          onClick={() => {
            if (mayOpen()) setOpen(true);
          }}
          onBlur={(event) => {
            close();
            onBlur?.(event);
          }}
          onChange={(event) => {
            if (mayOpen()) {
              setSearch(event.target.value);
              setActive(-1);
              setOpen(true);
            }
          }}
          onKeyDown={keyDown}
        />
        <span className="search-select-indicator">
          <AppIcon name="dropdown" size="sm" />
        </span>
      </div>
      {name && (
        <input
          type="hidden"
          name={name}
          value={value ?? ""}
          disabled={disabled}
        />
      )}
      {description && (
        <small id={`${inputId}-description`} className="field-description">
          {description}
        </small>
      )}
      {error && (
        <small id={`${inputId}-error`} role="alert">
          {error}
        </small>
      )}
      {expanded &&
        position &&
        createPortal(
          <div
            ref={menuRef}
            className="search-select-popover"
            style={position}
            onMouseDown={(event) => event.preventDefault()}
          >
            <div
              role="listbox"
              id={listId}
              aria-label={label}
              aria-busy={loading}
            >
              {available.map((option, index) => (
                <div
                  key={`${typeof option.value}:${option.value}`}
                  id={`${listId}-${index}`}
                  role="option"
                  aria-selected={option.value === value}
                  aria-disabled={
                    !!option.disabled || (required && option.value === "")
                  }
                  data-index={index}
                  data-active={index === active}
                  className="search-select-option"
                  onMouseMove={() => {
                    if (!option.disabled && !(required && option.value === ""))
                      setActive(index);
                  }}
                  onClick={() => choose(option)}
                >
                  <span>{option.label}</span>
                  {option.value === value && <AppIcon name="check" size="sm" />}
                </div>
              ))}
            </div>
            {loading ? (
              <div className="search-select-message" role="status">
                Loading options…
              </div>
            ) : requestError ? (
              <div className="search-select-message" role="alert">
                {requestError}
              </div>
            ) : (
              <>
                {!matches.length && (
                  <div className="search-select-message" role="status">
                    No matching results
                  </div>
                )}
                {menuMessage && (
                  <div className="search-select-message" role="status">
                    {menuMessage}
                  </div>
                )}
              </>
            )}
          </div>,
          modalRef?.current || document.body,
        )}
    </div>
  );
});
